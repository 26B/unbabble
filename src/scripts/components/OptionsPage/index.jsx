import { useState } from 'react';

import Languages from './Languages.jsx';
import Routing from './Routing.jsx';
import Types from './Types.jsx';

import { Flex, Button, Notice } from '@wordpress/components';
import getUBBSetting from '../../services/settings';

import { submitOptions } from '../../services/requests';
import { updateOptions } from '../../services/requests';

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

	const [ notice, setNotice ] = useState( null );
	const autoUpdate = getUBBSetting( 'optionsAutoUpdate', false );
	const [ canUpdate, setCanUpdate ] = useState(
		getUBBSetting( 'optionsCanUpdate', false )
	);

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
				if ( response?.data?.canUpdate !== undefined ) {
					setCanUpdate( response.data.canUpdate );
				}
				setNotice( 'success' );
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
				if ( response?.data?.canUpdate !== undefined ) {
					setCanUpdate( response.data.canUpdate );
				}

				setNotice( 'success' );
			} )
			.catch( ( error ) => setNotice( error.response.data.errors ) )
			.then( () => window.scrollTo( 0, 0 ) );
	};

	return (
		<>
			<form action="options.php" method="post">
				<Flex
					direction="row"
					style={ { width: '100%', justifyContent: 'normal' } }
				>
					<h1>Unbabble Settings</h1>
					{ ! autoUpdate && (
						<>
							<Button
								className="button button-primary"
								onClick={ submit }
							>
								Save
							</Button>
							<Button
								className="button button-primary"
								onClick={ update }
								disabled={ ! canUpdate }
							>
								Syncronize
							</Button>
						</>
					) }
				</Flex>
				{ notice === 'success' && (
					<Notice status="success" onRemove={ () => setNotice( '' ) }>
						Options have been updated.
					</Notice>
				) }
				{ notice !== null && typeof notice === 'object' && (
					<Notice status="error" onRemove={ () => setNotice( '' ) }>
						An error has occured while trying to update.
					</Notice>
				) }
				{ autoUpdate && (
					<Notice status="info" isDismissible={ false }>
						The options are read-only because they are in
						auto-update mode via the filter{ ' ' }
						<span
							style={ {
								backgroundColor: 'rgb(235,235,235)',
								padding: '2px 3px',
							} }
						>
							ubb_options
						</span>
						. If this is a mistake, please remove, or set as false,
						the constant{ ' ' }
						<span
							style={ {
								backgroundColor: 'rgb(235,235,235)',
								padding: '2px 3px',
							} }
						>
							UBB_OPTIONS_AUTO_UPDATE
						</span>
						.
					</Notice>
				) }
				<Languages
					languages={ languages }
					setLanguages={ setLanguages }
					defaultLanguage={ defaultLanguage }
					setDefaultLanguage={ setDefaultLanguage }
					readOnly={ autoUpdate }
				/>
				<Routing
					languages={ languages }
					defaultLanguage={ defaultLanguage }
					routing={ routing }
					setRouting={ setRouting }
					readOnly={ autoUpdate }
				/>
				<Types
					title="Post Types"
					addLabel="Add post type"
					selectLabel="Select a post type"
					addSelectedLabel="Add selected post type"
					types={ postTypes }
					setTypes={ setPostTypes }
					allTypes={ getUBBSetting( 'wpPostTypes', [] ) }
					readOnly={ autoUpdate }
				/>
				<Types
					title="Taxonomies"
					addLabel="Add taxonomy"
					selectLabel="Select a taxonomy"
					addSelectedLabel="Add selected taxonomy"
					types={ taxonomies }
					setTypes={ setTaxonomies }
					allTypes={ getUBBSetting( 'wpTaxonomies', [] ) }
					readOnly={ autoUpdate }
				/>
				{ ! autoUpdate && (
					<Button
						className="button button-primary"
						style={ { marginTop: 24 } }
						onClick={ submit }
					>
						Save
					</Button>
				) }
			</form>
		</>
	);
};

export default OptionsPage;
