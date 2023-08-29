import { useState } from 'react';

import {
	Button,
	SelectControl,
	Flex,
	CheckboxControl,
	Modal,
} from '@wordpress/components';

import useCopyPost from '../hooks/useCopyPost';
import { withLangContext } from './contexts/LangContext';
import getUBBSetting from '../services/settings';

const CreateTranslations = ({
	untranslatedLangs,
	postId,
	languagesInfo,
	refetchLangs,
}) => {
	const [current, setCurrent] = useState(untranslatedLangs[0]);
	const [shouldCopy, setShouldCopy] = useState(false);
	const [isDirtyModalOpen, setIsDirtyModalOpen] = useState(false);
	const { mutate, data, isLoading, isError } = useCopyPost(postId, current);

	const createUrlQuery = new URLSearchParams({
		ubb_source: postId,
		lang: current,
	});
	const createUrl = `${getUBBSetting(
		'admin_url',
		''
	)}/post-new.php?${createUrlQuery.toString()}`;

	const openDirtyModal = () => setIsDirtyModalOpen(true);
	const closeDirtyModal = () => setIsDirtyModalOpen(false);

	const changeValue = (newValue) => setCurrent(newValue);

	const toggleShouldCopy = () => setShouldCopy((state) => !state);

	const onSubmitCopy = () => mutate().then(() => refetchLangs());

	const onSubmit = () => {
		if (!shouldCopy) {
			window.location.href = createUrl;
			return;
		}

		if (isDirty) {
			openDirtyModal();
			return;
		}

		onSubmitCopy();
	};

	const selectOptions = untranslatedLangs.map((lang) => ({
		label: `${languagesInfo[lang].native_name} (${lang})`,
		value: lang,
	}));

	// TODO: isDirty can be unset when clicking the button directly after inserting text into the block editor.
	const isDirty =
		wp?.data?.select('core/editor').isEditedPostDirty() || false;

	return (
		<>
			<Flex direction="column" style={{ width: '100%' }}>
				<SelectControl
					label="Create Translations"
					onChange={changeValue}
					value={current}
					options={selectOptions}
				/>
				{/* TODO: Only show if yoast duplicate post is active. */}
				<CheckboxControl
					label="Copy content"
					help="When creating the translation, copy the content of this post."
					checked={shouldCopy}
					onChange={toggleShouldCopy}
				/>
				<Flex justify="end">
					<Button variant="secondary" onClick={onSubmit}>
						Create
					</Button>
				</Flex>
				{isError && 'ERROR!!!!'}
				{data && JSON.stringify(data)}
			</Flex>
			{isDirtyModalOpen && (
				<Modal title="Unsaved changes" onRequestClose={closeDirtyModal}>
					<p>The current post has unsaved changes.</p>
					<p>
						Copying with unsaved changes will not copy those changes
						to the new translation post.
					</p>
					<div
						style={{
							display: 'flex',
							justifyContent: 'end',
							gap: '10px',
							paddingTop: '20px',
						}}
					>
						<Button variant="secondary" onClick={closeDirtyModal}>
							Cancel
						</Button>
						<Button variant="primary" onClick={onSubmitCopy}>
							Copy
						</Button>
					</div>
				</Modal>
			)}
		</>
	);
};

export default withLangContext(CreateTranslations);
