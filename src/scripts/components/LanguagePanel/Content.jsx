import { useState, useEffect } from 'react';

import CreateTranslations from './CreateTranslations';
import useEditPost from '../../hooks/useEditPost';
import getUBBSetting from '../../services/settings';
import LangContext from '../../contexts/LangContext';
import PostLanguage from './PostLanguage';
import ListTranslations from './ListTranslations';
import UnlinkTranslations from './UnlinkTranslations';
import LinkTranslations from './LinkTranslations';
import { linkPost } from '../../services/requests';
import { getQueryVar } from '../../services/searchQuery';

// Because Gutenberg.
let sourceToLink       = new URLSearchParams(window.location.search).get("ubb_source");
let unsubscribeLinking = null;

const Language = () => {
	const postId = wp?.data?.select('core/editor')?.getCurrentPostId() ?? getQueryVar('post');
	const { data, refetch, isLoading, isError, setIsLoading, setIsError } = useEditPost(postId);

	// Link post to source post if ubb_source is present in the URL.
	useEffect(() => {

		// Don't run on classic editor.
		if ( postId === undefined ) {
			return;
		}

		// Don't subscribe if sourceToLink is not set.
		if ( ! sourceToLink ) {
			return;
		}

		// Save the unsubscribe function to be able to unsubscribe later.
		unsubscribeLinking = wp.data.subscribe(() => {
			// Don't run if sourceToLink is not set.
			if ( ! sourceToLink ) {
				return;
			}

			// Check if post save request was successful and if the post is new.
			const didPostSaveRequestSucceed = wp.data.select('core/editor').didPostSaveRequestSucceed();
			const isEditedPostNew           = wp.data.select('core/editor').isEditedPostNew();
			if ( ! didPostSaveRequestSucceed || isEditedPostNew ) {
				return;
			}

			// Set loading to keep the user from interacting with the interface.
			setIsLoading(true);

			// Try to link the post to the source post.
			linkPost(wp.data.select('core/editor').getCurrentPostId(), sourceToLink)
				.then(() => {
					// Refetch data to update the interface.
					refetch();
				})
				.catch(() => {
					// Set error to show the user that something went wrong.
					setIsError(true);
				})
				.then(() => {
					// Set loading to false to allow the user to interact with the interface.
					setIsLoading(false);
				});

			// Reset sourceToLink to prevent linking the post multiple times.
			sourceToLink = null;
			unsubscribeLinking();
		});

		return unsubscribeLinking
	}, []);

	if (isLoading) {
		return 'Loading...'; // TODO: Add spinner
	}

	if (isError) {
		return 'Error fetching post language data.';
	}

	const isEditedPostNew = wp?.data?.select('core/editor')?.isEditedPostNew() ?? false;
	if (isEditedPostNew || !data || !data.translations) {
		const url              = new URL(window.location.href);
		const ubb_source       = url.searchParams.get("ubb_source");
		const ubb_source_title = getUBBSetting('source_title', '');
		const ubb_source_url   = getUBBSetting('source_edit_url', '');

		return (
			<>
				<input
					hidden
					readOnly
					id='ubb_lang'
					name="ubb_lang"
					value={getUBBSetting('current_lang', '')}
					/>
				<span>Post has no language data.</span>
				{ubb_source && (
					<>
						<br/><br/>
						<span>
							Post is being linked as a translation to post
							"<a href={ubb_source_url} target="_blank">{ubb_source_title}</a>".
						</span>
					</>
				)}
			</>
		);
	}

	const { language, translations: translatedLangs } = data;
	const currentLang = getUBBSetting('current_lang', '');
	const languages = getUBBSetting('languages', {});
	const languagesInfo = getUBBSetting('languagesInfo', {});

	const untranslatedLangs = languages.filter(
		(lang) =>
			(language === null || lang !== language) &&
			!translatedLangs
				.map((translatedLang) => translatedLang.language)
				.includes(lang)
	);

	const badLanguage = language !== null && !languages.includes(language);

	return (
		<LangContext.Provider
			value={{
				currentLang,
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
			{!badLanguage && untranslatedLangs.length > 0 && (
				<>
					<hr />
					<CreateTranslations />
				</>
			)}
			{!badLanguage && translatedLangs.length < 1 && (
				<>
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
					<p>
						This post currently has no translation group. You can
						link it to existing posts in other languages.
					</p>
					<LinkTranslations />
				</>
			)}
			{translatedLangs.length > 0 && (
				<>
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
