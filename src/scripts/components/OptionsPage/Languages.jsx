import { useState } from 'react';

import {
	Flex,
	Button,
	SelectControl,
	Modal,
	DropdownMenu,
	MenuGroup,
	MenuItem,
} from '@wordpress/components';

import { menu, seen, starFilled, trash } from '@wordpress/icons';

import getUBBSetting from '../../services/settings';

const AllowedLanguages = ({
	languages,
	setLanguages,
	defaultLanguage,
	setDefaultLanguage,
}) => {
	const [showAddLanguageModal, setShowAddLanguageModal] = useState(false);
	const [showRemoveLanguageModal, setShowRemoveLanguageModal] =
		useState(false);
	const [languageToAdd, setLanguageToAdd] = useState('');
	const [languageToRemove, setLanguageToRemove] = useState('');

	const addLanguage = () => {
		// TODO: unique.
		setLanguages(
			languages.concat([{ language: languageToAdd, hidden: false }])
		);
		setShowAddLanguageModal(false);
		setLanguageToAdd('');
	};

	const removeLanguage = () => {
		setLanguages(
			languages.filter((lang) => lang.language !== languageToRemove)
		);
		setShowRemoveLanguageModal(false);
		setLanguageToRemove('');
	};

	const changeDefaultLanguage = (newDefault) => {
		setDefaultLanguage(newDefault);
		if (languages.find(({ language }) => language === newDefault).hidden) {
			toggleHideLanguage(newDefault);
		}
	};

	const openAddLanguageModal = () => {
		setShowAddLanguageModal(true);
		setLanguageToAdd('');
	};
	const closeAddLanguageModal = () => {
		setShowAddLanguageModal(false);
		setLanguageToAdd('');
	};

	const openRemoveLanguageModal = (language) => {
		setShowRemoveLanguageModal(true);
		setLanguageToRemove(language);
	};
	const closeRemoveLanguageModal = () => {
		setShowRemoveLanguageModal(false);
		setLanguageToRemove('');
	};

	const toggleHideLanguage = (language) => {
		const newLanguages = languages.concat();
		const lang = newLanguages.find((lang) => lang.language === language);
		newLanguages.find((lang) => lang.language === language).hidden =
			!lang.hidden;
		setLanguages(newLanguages);
	};

	return (
		<>
			<div
				style={{
					display: 'grid',
					flexWrap: 'wrap',
					paddingBottom: '20px',
					gap: '8px',
				}}
			>
				<Flex direction="row">
					<div>
						<div
							style={{
								display: 'grid',
								flexWrap: 'wrap',
								gap: '8px',
								maxHeight: '312px',
								overflow: 'scroll',
								padding: '20px',
								background: 'white',
								border: '2px solid #ccc',
								borderRadius: '5px',
							}}
						>
							<table>
								<tbody>
									{languages.length > 0 &&
										languages
											.sort((a, b) => {
												if (
													a.language ===
													defaultLanguage
												) {
													return -1;
												}
												if (
													b.language ===
													defaultLanguage
												) {
													return 1;
												}
												return 0;
											})
											.map(({ language, hidden }) => {
												const label = getUBBSetting(
													'wpLanguages',
													[]
												).find(
													(wpLang) =>
														wpLang.code === language
												)?.label;

												const isDefault =
													language ===
													defaultLanguage;

												return (
													<tr key={language}>
														<td>{label}</td>
														<td
															style={{
																paddingLeft:
																	'20px',
																color: '#888',
															}}
														>
															{isDefault && (
																<p
																	style={{
																		margin: 0,
																	}}
																>
																	Default
																	language
																</p>
															)}
														</td>
														<td
															style={{
																paddingLeft:
																	'20px',
															}}
														>
															{hidden && (
																<p
																	style={{
																		color: 'rgb(230, 74, 74)',
																	}}
																>
																	Hidden
																</p>
															)}
														</td>
														<td
															style={{
																paddingLeft:
																	'20px',
															}}
														>
															{isDefault && (
																<Button
																	icon={menu}
																	style={{
																		padding: 6,
																		fill: '#d5d5d5',
																	}}
																	disabled
																/>
															)}
															{!isDefault && (
																<DropdownMenu
																	icon={menu}
																>
																	{({
																		onClose,
																	}) => (
																		<>
																			<MenuGroup>
																				<MenuItem
																					icon={
																						seen
																					}
																					onClick={() =>
																						toggleHideLanguage(
																							language
																						)
																					}
																				>
																					{hidden
																						? 'Show'
																						: 'Hide'}
																				</MenuItem>
																				<MenuItem
																					icon={
																						starFilled
																					}
																					onClick={() => {
																						changeDefaultLanguage(
																							language
																						);
																						onClose();
																					}}
																				>
																					Set
																					as
																					default
																				</MenuItem>
																			</MenuGroup>
																			<MenuGroup>
																				<MenuItem
																					icon={
																						trash
																					}
																					onClick={() => {
																						openRemoveLanguageModal(
																							language
																						);
																						onClose();
																					}}
																				>
																					Remove
																				</MenuItem>
																			</MenuGroup>
																		</>
																	)}
																</DropdownMenu>
															)}
														</td>
													</tr>
												);
											})}
								</tbody>
							</table>
						</div>
					</div>
					<div
						style={{
							display: 'grid',
							gap: 8,
							alignSelf: 'baseline',
						}}
					>
						<Button
							variant="secondary"
							onClick={openAddLanguageModal}
						>
							Add language
						</Button>
					</div>
					{showAddLanguageModal && (
						<Modal
							title="Add new Language"
							onRequestClose={closeAddLanguageModal}
						>
							<SelectControl
								value={languageToAdd}
								onChange={(selection) => {
									setLanguageToAdd(selection);
								}}
							>
								<option value="">Select a language:</option>
								{getUBBSetting('wpLanguages', [])
									.filter(
										(language) =>
											!languages.includes(language.code)
									)
									.map((language) => (
										<option
											value={language.code}
											key={language.code}
										>
											{language.label}
										</option>
									))}
							</SelectControl>
							{languageToAdd && (
								<Button
									variant="secondary"
									onClick={addLanguage}
								>
									Add Selected Language
								</Button>
							)}
						</Modal>
					)}
					{showRemoveLanguageModal && languageToRemove && (
						<Modal
							title="Removing Language"
							onRequestClose={closeRemoveLanguageModal}
						>
							<p>
								Are you sure you want to remove{' '}
								<b>
									{
										getUBBSetting('wpLanguages', []).find(
											(wpLang) =>
												wpLang.code === languageToRemove
										)?.label
									}
								</b>
								?
							</p>
							<Button
								variant="secondary"
								onClick={removeLanguage}
							>
								Remove
							</Button>
							<Button
								variant="secondary"
								onClick={closeRemoveLanguageModal}
							>
								Cancel
							</Button>
						</Modal>
					)}
				</Flex>
			</div>
		</>
	);
};

const Languages = ({
	languages,
	setLanguages,
	defaultLanguage,
	setDefaultLanguage,
}) => {
	return (
		<>
			<h3>Languages</h3>
			<Flex
				direction="row"
				style={{ width: '100%', justifyContent: 'normal' }}
			>
				<AllowedLanguages
					languages={languages}
					setLanguages={setLanguages}
					defaultLanguage={defaultLanguage}
					setDefaultLanguage={setDefaultLanguage}
				/>
			</Flex>
		</>
	);
};

export default Languages;
