import { useState } from 'react';

import CreateTranslations from './CreateTranslations';
import useEditPost from '../hooks/useEditPost';
import { getQueryVar } from '../services/searchQuery';
import getUBBSetting from '../services/settings';
import LangContext from './contexts/LangContext';
import PostLanguage from './PostLanguage';
import ListTranslations from './ListTranslations';
import UnlinkTranslations from './UnlinkTranslations';
import LinkTranslations from './LinkTranslations';

const Language = () => {
	const [postId, setPostId] = useState(getQueryVar('post'));
	const { data, refetch, isLoading, isError } = useEditPost(postId);
	const [, setIsSavingMetaboxes] = useState(
		wp?.data?.select('core/edit-post')?.isSavingMetaBoxes() || false
	);

	wp.data.subscribe(() => {
		setIsSavingMetaboxes((prev) => {
			const current = wp.data
				.select('core/edit-post')
				.isSavingMetaBoxes();
			if (prev && !current) {
				setPostId(wp.data.select('core/editor').getCurrentPostId());
			}
			return current;
		});
	});

	if (isLoading) {
		return 'Loading...'; // TODO: Add spinner
	}

	if (isError) {
		return 'Error fetching post language data.';
	}

	// TODO: If ubb_source is present, show info about being linked to post X on create.

	if (!data || !data.translations) {
		return 'Post has no language data.';
	}

	const { language, translations: translatedLangs } = data;
	const languages = getUBBSetting('languages', {});
	const languagesInfo = getUBBSetting('languagesInfo', {});

	const untranslatedLangs = languages.filter(
		(lang) =>
			lang !== language &&
			!translatedLangs
				.map((translatedLang) => translatedLang.language)
				.includes(lang)
	);

	return (
		<LangContext.Provider
			value={{
				currentLang: language,
				postId: data.postId,
				postLanguage: data.language,
				languages,
				languagesInfo,
				translatedLangs,
				untranslatedLangs,
				refetchLangs: refetch,
			}}
		>
			<PostLanguage />
			<ListTranslations />
			{untranslatedLangs.length > 0 && (
				<>
					<hr />
					<CreateTranslations />
				</>
			)}
			<hr />
			<p
				style={{
					textTransform: 'uppercase',
					fontSize: 11,
					fontWeight: 500,
				}}
			>
				linking
			</p>
			{translatedLangs.length < 1 && (
				<>
					<p>
						This post currently has no translation group. You can
						link it to existing posts in other languages.
					</p>
					<LinkTranslations />
				</>
			)}
			{translatedLangs.length > 0 && (
				<>
					<p>
						This post is currently in a translation group. If you
						wish to change, you must first unlink from the current
						group.
					</p>
					<UnlinkTranslations />
				</>
			)}
		</LangContext.Provider>
	);
};

export default Language;
