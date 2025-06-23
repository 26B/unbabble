import { Flex, SelectControl, TextControl } from '@wordpress/components';
import getUBBSetting from '../../services/settings';

const Routing = ( {
	languages,
	defaultLanguage,
	routing,
	setRouting,
	readOnly,
} ) => {
	const setRouter = ( router ) => {
		setRouting( { ...routing, router } );
	};

	const setRouterDirectory = ( language, directory ) => {
		setRouting( {
			...routing,
			router_options: {
				...routing.router_options,
				directories: {
					...routing.router_options?.directories,
					[ language ]: directory,
				},
			},
		} );
	};

	// TODO: Add a display to show what the link will look like in either case.
	return (
		<>
			<h3>Routing</h3>
			<Flex
				direction="column"
				style={ { width: 'fit-content', marginBottom: 24 } }
			>
				<SelectControl
					value={ routing.router }
					onChange={ ( selection ) => {
						setRouter( selection );
					} }
					label="Routing type"
					__nextHasNoMarginBottom
					disabled={ readOnly }
				>
					<option value="directory">Directory</option>
					<option value="query_var">Query Var</option>
				</SelectControl>
				{ routing.router === 'directory' && (
					<>
						<h4>Directory settings</h4>
						<div
							style={ {
								display: 'grid',
								flexWrap: 'wrap',
								gap: '8px',
								maxHeight: '312px',
								overflow: 'scroll',
								padding: '20px',
								background: 'white',
								border: '2px solid #ccc',
								borderRadius: '5px',
							} }
						>
							{ languages.map( ( { language } ) => {
								const label = getUBBSetting(
									'wpLanguages',
									[]
								).find(
									( wpLang ) => wpLang.code === language
								)?.label;

								const isDefault = language === defaultLanguage;
								return (
									<Flex
										direction="row"
										style={ {
											justifyContent: 'end',
											gap: 32,
										} }
										key={ language }
									>
										<div>{ label }</div>
										<TextControl
											value={
												isDefault
													? '/'
													: routing.router_options
															?.directories?.[
															language
													  ] ?? ''
											}
											onChange={ ( value ) =>
												setRouterDirectory(
													language,
													value
												)
											}
											// TODO: hard to tell that its disabl	ed.
											placeholder={
												isDefault ? '/' : language
											}
											disabled={ readOnly || isDefault }
											__nextHasNoMarginBottom
										/>
									</Flex>
								);
							} ) }
						</div>
					</>
				) }
			</Flex>
		</>
	);
};

export default Routing;
