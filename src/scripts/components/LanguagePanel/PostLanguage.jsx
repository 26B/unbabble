import { useState } from 'react';

import { withLangContext } from '../../contexts/LangContext';
import {
	PanelRow,
	Button,
	SelectControl,
	Flex,
	FlexItem,
	Tooltip,
} from '@wordpress/components';
import useChangeLanguagePost from '../../hooks/useChangeLanguagePost';

const PostLanguage = ({
	postLanguage,
	currentLang,
	languages,
	languagesInfo,
	translatedLangs,
	refetchLangs,
	postId,
}) => {
	const [isEditOpen, setIsEditOpen] = useState(false);
	const [editLanguage, setEditLanguage] = useState(
		postLanguage === null || !languages.includes(postLanguage)
			? null
			: postLanguage
	);
	const { mutate, isLoading, isError } = useChangeLanguagePost(postId);

	const openEdit = () => setIsEditOpen(true);
	const closeEdit = () => setIsEditOpen(false);

	const submitLangChange = () => {
		if (editLanguage === postLanguage) {
			closeEdit();
			return;
		}
		mutate(editLanguage).then(() => refetchLangs());
	};

	if (isLoading) {
		return 'Loading...'; // TODO: Add spinner
	}

	let languageOptions = languages.map((lang) => {
		return {
			label: `${languagesInfo[lang].native_name} (${lang})`,
			value: lang,
			disabled:
				(postLanguage === null || lang !== postLanguage) &&
				translatedLangs.find(
					(translatedLang) => translatedLang.language === lang
				) !== undefined,
		};
	});

	let langLabel = null;
	let showUnknownError = false;
	if (postLanguage === null) {
		languageOptions = [
			{
				label: 'Select a language',
				value: '',
				disabled: false,
			},
			...languageOptions,
		];
		langLabel = 'Select a language';
	} else if (!languages.includes(postLanguage)) {
		languageOptions = [
			{
				label: 'Select a language',
				value: '',
				disabled: false,
			},
			...languageOptions,
		];
		langLabel = `Unknown language ${postLanguage}`;
		showUnknownError = true;
	}

	if (langLabel === null) {
		langLabel = `${languagesInfo[postLanguage].native_name} (${postLanguage})`;
	}

	return (
		<PanelRow>
			{!isEditOpen && (
				<div
					style={{
						display: 'grid',
						justifyContent: 'space-between',
						width: '100%',
					}}
				>
					<span style={{ gridColumn: '1/2' }}>
						{showUnknownError && (
							<span style={{ marginRight: '4px' }}>
								<Tooltip
									text="This language is not set in the Unbabble options. Please select a correct language."
									delay="500"
								>
									<span
										className="dashicons dashicons-warning"
										style={{ color: 'FireBrick' }}
									></span>
								</Tooltip>
							</span>
						)}
						{langLabel}
					</span>
					<Button
						style={{ gridColumn: '2/2' }}
						variant="link"
						onClick={openEdit}
					>
						Edit
					</Button>
				</div>
			)}
			{isEditOpen && (
				<>
					<FlexItem isBlock>
						<Flex direction="column" align="stretch">
							<SelectControl
								style={{ width: '100%' }}
								value={
									editLanguage === null ? '' : editLanguage
								}
								options={languageOptions}
								onChange={(newEditLanguage) =>
									setEditLanguage(newEditLanguage)
								}
								__nextHasNoMarginBottom
							/>
							<Flex gap="2" justify="end">
								<Button
									style={{ boxSizing: 'border-box' }}
									variant="tertiary"
									onClick={closeEdit}
								>
									Cancel
								</Button>
								<Button
									style={{ boxSizing: 'border-box' }}
									variant="primary"
									onClick={submitLangChange}
								>
									Save
								</Button>
							</Flex>
						</Flex>
					</FlexItem>
				</>
			)}
		</PanelRow>
	);
};

export default withLangContext(PostLanguage);
