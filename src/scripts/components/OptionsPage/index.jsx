import { useState } from 'react';

import Languages from './Languages.jsx';
import Routing from './Routing.jsx';
import Types from './Types.jsx';

import { Flex, Button, Notice } from '@wordpress/components';
import getUBBSetting from '../../services/settings';

import { submitOptions, updateOptions } from '../../services/requests';

const Buttons = ( {
	isDirty,
	submit,
	update,
	hasFilterSettings,
	hasManualChanges,
} ) => (
	<>
		<Button
			className="button button-primary"
			onClick={ submit }
			disabled={ ! isDirty }
		>
			Save
		</Button>
		{ hasFilterSettings && (
			<Button
				className="button button-primary"
				onClick={ update }
				disabled={ ! hasManualChanges || ! hasFilterSettings }
			>
				Reset to filter options
			</Button>
		) }
	</>
);

const HighlightText = ( { text } ) => (
	<span
		style={ {
			backgroundColor: 'rgb(235,235,235)',
			padding: '2px 3px',
		} }
	>
		{ text }
	</span>
);

const OptionsPage = ( {} ) => {
	const [ options, setOptions ] = useState( getUBBSetting( 'options', [] ) );
	const [ postTypes, setPostTypes ] = useState( options?.post_types );
	const [ taxonomies, setTaxonomies ] = useState( options?.taxonomies );
	const [ languages, setLanguages ] = useState(
		options?.allowed_languages.map( ( language ) => {
			return {
				language,
				hidden: options?.hidden_languages?.includes( language ),
			};
		} )
	);
	const [ defaultLanguage, setDefaultLanguage ] = useState(
		options?.default_language
	);
	const [ routing, setRouting ] = useState( {
		router: options?.router,
		router_options: options?.router_options,
	} );

	const [ isDirty, setIsDirty ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const readOnly = getUBBSetting( 'settings_read_only', false );
	const [ hasManualChanges, setHasManualChanges ] = useState(
		getUBBSetting( 'settings_manual_changes', false )
	);
	const hasFilterSettings = getUBBSetting( 'settings_has_filter', false );

	const setValues = ( data ) => {
		setPostTypes( data?.post_types );
		setTaxonomies( data?.taxonomies );
		setLanguages(
			data?.allowed_languages.map( ( language ) => {
				return {
					language,
					hidden: data?.hidden_languages?.includes( language ),
				};
			} )
		);
		setDefaultLanguage( data?.default_language );
		setRouting( {
			router: data?.router,
			router_options: data?.router_options,
		} );
	};

	const submit = () => {
		setNotice( '' );
		submitOptions( {
			languages,
			defaultLanguage,
			routing,
			postTypes,
			taxonomies,
		} )
			.then( ( response ) => {
				if ( response?.data?.options !== undefined ) {
					// TODO: not doing much
					setOptions( response.data.options );
					setValues( response.data.options );
				}
				if ( response?.data?.has_manual_changes !== undefined ) {
					setHasManualChanges( response.data.has_manual_changes );
				}
				setNotice( 'success' );
				setIsDirty( false );
			} )
			.catch( ( error ) => setNotice( error.response.data.errors ) )
			.then( () => window.scrollTo( 0, 0 ) );
	};

	const update = () => {
		setNotice( '' );
		updateOptions()
			.then( ( response ) => {
				if ( response?.data?.options !== undefined ) {
					// TODO: not doing much
					setOptions( response.data.options );
					setValues( response.data.options );
				}
				if ( response?.data?.has_manual_changes !== undefined ) {
					setHasManualChanges( response.data.has_manual_changes );
				}

				setNotice( 'success' );
				setIsDirty( false );
			} )
			.catch( ( error ) => setNotice( error.response.data.errors ) )
			.then( () => window.scrollTo( 0, 0 ) );
	};

	const setDirty = ( setState ) => ( value ) => {
		setState( value );
		setIsDirty( true );
	};

	return (
		<>
			<form action="options.php" method="post">
				<Flex
					direction="row"
					style={ { width: '100%', justifyContent: 'normal' } }
				>
					<h1>Unbabble Settings</h1>
					{ ! readOnly && (
						<Buttons
							isDirty={ isDirty }
							submit={ submit }
							update={ update }
							hasFilterSettings={ hasFilterSettings }
							hasManualChanges={ hasManualChanges }
						/>
					) }
				</Flex>
				<div style={ { display: 'grid', gap: '10px' } }>
					{ readOnly && (
						<Notice status="info" isDismissible={ false }>
							The settings are read-only due to the constant{ ' ' }
							<HighlightText text="UBB_SETTINGS_READONLY" />.
						</Notice>
					) }
					{ ! hasManualChanges && hasFilterSettings && (
						<Notice status="info" isDismissible={ false }>
							Settings are being automatically updated via the
							filter <HighlightText text="ubb_options" />.
						</Notice>
					) }
					{ hasManualChanges && hasFilterSettings && (
						<Notice status="info" isDismissible={ false }>
							Settings have been manually edited and are no longer
							being automatically updated.
						</Notice>
					) }
					{ notice === 'success' && (
						<Notice
							status="success"
							onRemove={ () => setNotice( '' ) }
						>
							Settings have been updated.
						</Notice>
					) }
					{ notice !== null && typeof notice === 'object' && (
						<Notice
							status="error"
							onRemove={ () => setNotice( '' ) }
						>
							An error has occured while trying to update.
						</Notice>
					) }
				</div>
				<Languages
					languages={ languages }
					setLanguages={ setDirty( setLanguages ) }
					defaultLanguage={ defaultLanguage }
					setDefaultLanguage={ setDirty( setDefaultLanguage ) }
					readOnly={ readOnly }
				/>
				<Routing
					languages={ languages }
					defaultLanguage={ defaultLanguage }
					routing={ routing }
					setRouting={ setDirty( setRouting ) }
					readOnly={ readOnly }
				/>
				<Types
					title="Post Types"
					addLabel="Add post type"
					selectLabel="Select a post type"
					addSelectedLabel="Add selected post type"
					types={ postTypes }
					setTypes={ setDirty( setPostTypes ) }
					allTypes={ getUBBSetting( 'wpPostTypes', [] ) }
					readOnly={ readOnly }
				/>
				<Types
					title="Taxonomies"
					addLabel="Add taxonomy"
					selectLabel="Select a taxonomy"
					addSelectedLabel="Add selected taxonomy"
					types={ taxonomies }
					setTypes={ setDirty( setTaxonomies ) }
					allTypes={ getUBBSetting( 'wpTaxonomies', [] ) }
					readOnly={ readOnly }
				/>
				{ ! readOnly && (
					<Buttons
						isDirty={ isDirty }
						submit={ submit }
						update={ update }
						hasFilterSettings={ hasFilterSettings }
						hasManualChanges={ hasManualChanges }
					/>
				) }
			</form>
		</>
	);
};

export default OptionsPage;
