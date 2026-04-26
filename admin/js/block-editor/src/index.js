import domReady from '@wordpress/dom-ready';
import { registerPlugin } from '@wordpress/plugins';
import * as editorPkg from '@wordpress/editor';
import * as editPostPkg from '@wordpress/edit-post';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEntityRecords } from '@wordpress/core-data';
import {
	TreeSelect,
	Button,
	TextControl,
	Spinner,
	Notice,
} from '@wordpress/components';
import { useEffect, useMemo, useState, Fragment } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

// Canonical location since WP 6.6; fall back to the legacy export for older WP.
const PluginDocumentSettingPanel =
	editorPkg.PluginDocumentSettingPanel ||
	editPostPkg.PluginDocumentSettingPanel;

const TERMS_QUERY = {
	per_page: 100,
	_fields: 'id,name,parent',
	context: 'view',
	orderby: 'name',
	order: 'asc',
};

function buildTree( terms, parentId = 0 ) {
	return terms
		.filter( ( t ) => t.parent === parentId )
		.map( ( t ) => ( {
			name: t.name,
			id: String( t.id ),
			children: buildTree( terms, t.id ),
		} ) );
}

function flattenIndented( tree, depth = 0 ) {
	const out = [];
	tree.forEach( ( node ) => {
		out.push( { id: node.id, name: node.name, depth } );
		if ( node.children.length ) {
			out.push( ...flattenIndented( node.children, depth + 1 ) );
		}
	} );
	return out;
}

function TaxonomyPanel( { taxonomy } ) {
	const postType = useSelect(
		( select ) => select( 'core/editor' ).getCurrentPostType(),
		[]
	);

	if ( ! taxonomy.post_types.includes( postType ) ) {
		return null;
	}

	return <TaxonomyPanelInner taxonomy={ taxonomy } />;
}

function TaxonomyPanelInner( { taxonomy } ) {
	const {
		slug,
		rest_base: restBase,
		type,
		indented,
		allow_new_terms: allowNewTerms,
		force_selection: forceSelection,
		panel_title: panelTitle,
	} = taxonomy;

	const currentTerms = useSelect(
		( select ) =>
			select( 'core/editor' ).getEditedPostAttribute( restBase ) || [],
		[ restBase ]
	);

	const editorActions = useDispatch( 'core/editor' );
	const editPostActions = useDispatch( 'core/edit-post' );
	const { saveEntityRecord, invalidateResolution } = useDispatch( 'core' );

	const { editPost } = editorActions;
	// `removeEditorPanel` moved to `core/editor` in WP 6.6; `core/edit-post`
	// proxies for back compat. Prefer the canonical store.
	const removeEditorPanel =
		editorActions.removeEditorPanel || editPostActions.removeEditorPanel;

	const { records: terms, hasResolved, totalItems } = useEntityRecords(
		'taxonomy',
		slug,
		TERMS_QUERY
	);

	const canCreate = useSelect(
		( select ) =>
			select( 'core' ).canUser( 'create', {
				kind: 'taxonomy',
				name: slug,
			} ),
		[ slug ]
	);

	const [ newTermName, setNewTermName ] = useState( '' );
	const [ creating, setCreating ] = useState( false );
	const [ createError, setCreateError ] = useState( null );

	const tree = useMemo( () => buildTree( terms || [] ), [ terms ] );

	useEffect( () => {
		removeEditorPanel( `taxonomy-panel-${ slug }` );
	}, [ slug, removeEditorPanel ] );

	const selectedId = currentTerms.length ? String( currentTerms[ 0 ] ) : '';
	const truncated =
		typeof totalItems === 'number' && terms && totalItems > terms.length;

	const selectTerm = ( idStr ) => {
		const id = parseInt( idStr, 10 );
		editPost( { [ restBase ]: id ? [ id ] : [] } );
	};

	const addTerm = async () => {
		const name = newTermName.trim();
		if ( ! name ) {
			return;
		}
		setCreating( true );
		setCreateError( null );
		try {
			const created = await saveEntityRecord( 'taxonomy', slug, { name } );
			// saveEntityRecord adds the record to the entity store but does not
			// refresh existing query lists; invalidate so the new term appears.
			invalidateResolution( 'getEntityRecords', [
				'taxonomy',
				slug,
				TERMS_QUERY,
			] );
			setNewTermName( '' );
			selectTerm( String( created.id ) );
		} catch ( e ) {
			setCreateError( e.message || __( 'Could not create term', 'of-cme' ) );
		} finally {
			setCreating( false );
		}
	};

	const renderTermList = () => {
		if ( ! hasResolved ) {
			return <Spinner />;
		}
		if ( ! terms || terms.length === 0 ) {
			return <p>{ __( 'No terms found.', 'of-cme' ) }</p>;
		}
		if ( type === 'select' ) {
			// With force_selection on, suppress the "— Select —" option once a
			// term has been picked so the user can't deselect back to empty.
			// Initial empty state still shows it; the server-side filter
			// substitutes the first term if the post is saved with none.
			const showNoOption = ! forceSelection || ! selectedId;
			return (
				<TreeSelect
					label=""
					noOptionLabel={
						showNoOption ? __( '— Select —', 'of-cme' ) : undefined
					}
					tree={ tree }
					selectedId={ selectedId }
					onChange={ selectTerm }
				/>
			);
		}
		return (
			<Fragment>
				<ul className="of-cme-radio-list">
					{ flattenIndented( tree ).map( ( node ) => (
						<li
							key={ node.id }
							style={ {
								paddingLeft: indented
									? `${ node.depth * 16 }px`
									: 0,
								listStyle: 'none',
								margin: '4px 0',
							} }
						>
							<label>
								<input
									type="radio"
									name={ `of-cme-${ slug }` }
									value={ node.id }
									checked={ selectedId === node.id }
									onChange={ () => selectTerm( node.id ) }
								/>{ ' ' }
								{ node.name }
							</label>
						</li>
					) ) }
				</ul>
				{ /* Mirrors the classic editor's "Clear" affordance, which
				   Taxonomy_Single_Term renders only when force_selection is
				   false. Without this, the toggle has no visible effect on
				   radio-type taxonomies in the Block Editor. */ }
				{ ! forceSelection && selectedId && (
					<Button
						variant="link"
						onClick={ () => selectTerm( '' ) }
						style={ { marginTop: 8 } }
					>
						{ __( 'Clear', 'of-cme' ) }
					</Button>
				) }
			</Fragment>
		);
	};

	return (
		<PluginDocumentSettingPanel
			name={ `of-cme-${ slug }` }
			title={ panelTitle }
			className={ `of-cme-panel of-cme-panel-${ slug }` }
		>
			{ renderTermList() }
			{ truncated && (
				<Notice status="warning" isDismissible={ false }>
					{ sprintf(
						/* translators: 1: shown count, 2: total count */
						__(
							'Showing %1$d of %2$d terms. Some terms are not listed.',
							'of-cme'
						),
						terms.length,
						totalItems
					) }
				</Notice>
			) }
			{ allowNewTerms && canCreate && (
				<div className="of-cme-add-term" style={ { marginTop: 12 } }>
					<TextControl
						label={ __( 'Add new term', 'of-cme' ) }
						value={ newTermName }
						onChange={ setNewTermName }
						disabled={ creating }
					/>
					<Button
						variant="secondary"
						onClick={ addTerm }
						disabled={ creating || ! newTermName.trim() }
					>
						{ creating
							? __( 'Adding…', 'of-cme' )
							: __( 'Add', 'of-cme' ) }
					</Button>
					{ createError && (
						<Notice status="error" isDismissible={ false }>
							{ createError }
						</Notice>
					) }
				</div>
			) }
		</PluginDocumentSettingPanel>
	);
}

domReady( () => {
	const taxonomies = window.ofCmeBlockEditor?.taxonomies || [];
	if ( ! taxonomies.length ) {
		return;
	}
	registerPlugin( 'of-cme-panel', {
		render: () => (
			<Fragment>
				{ taxonomies.map( ( tax ) => (
					<TaxonomyPanel key={ tax.slug } taxonomy={ tax } />
				) ) }
			</Fragment>
		),
	} );
} );
