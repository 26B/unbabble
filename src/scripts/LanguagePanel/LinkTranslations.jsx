import { useState } from 'react';

import { Button, Notice, Modal } from '@wordpress/components';

import Collapse from '../components/Collapse';
import useLinkablePosts from '../hooks/useLinkablePosts';
import { withLangContext } from './contexts/LangContext';
import useLinkPost from '../hooks/useLinkPost';

const LinkOption = ({ postId, refetchLangs, posts, source }) => {
	const { mutate, isLoading, isSuccess, isError } = useLinkPost(
		postId,
		source
	);

	const onLink = () => mutate().then(() => refetchLangs());

	const main_post = posts[0];

	return (
		<div
			style={{
				display: 'flex',
				justifyContent: 'space-between',
				border: '1px solid #e0e0e0',
				padding: '8px',
			}}
		>
			<div
				style={{
					display: 'grid',
					gridTemplateColumns: 'repeat(1, 1fr)',
					alignContent: 'center',
				}}
			>
				{posts.length === 1 && (
					<summary>
						{'(' + main_post.lang + ') ' + main_post.title}
					</summary>
				)}
				{posts.length > 1 && (
					<Collapse
						title={'(' + main_post.lang + ') ' + main_post.title}
					>
						<div
							style={{
								display: 'flex',
								flexWrap: 'wrap',
								marginLeft: 20,
							}}
						>
							<h4 style={{ marginTop: 0 }}>
								Other translations in the group:
							</h4>
							{posts.slice(1).map(({ title, ID, lang }) => (
								<div
									style={{
										width: '100%',
										justifyContent: 'space-between',
									}}
								>
									<span>({lang}) </span>
									<span>{title} </span>
								</div>
							))}
						</div>
					</Collapse>
				)}
			</div>
			<Button
				variant="secondary"
				onClick={onLink}
				disabled={isLoading || isSuccess}
				style={{ height: 'min-content', margin: 'auto 0 auto 32px' }}
			>
				{!isSuccess && 'Link'}
				{isSuccess && 'Linked'}
			</Button>
		</div>
	);
};

const LinkTranslations = ({ postId, refetchLangs }) => {
	const [isModalOpen, setIsModalOpen] = useState(false);
	const { data, refetch, isLoading, isError } = useLinkablePosts(postId, 1);
	const [page, setPage] = useState(1);
	const [totalPages, setTotalPages] = useState(data?.pages || 1);

	const openModal = () => setIsModalOpen(true);
	const closeModal = () => setIsModalOpen(false);
	const previousPage = () => {
		if (page <= 1) {
			return;
		}
		setPage(page - 1);
		refetch(page - 1);
	};

	const nextPage = () => {
		if (page >= totalPages) {
			return;
		}
		setPage(page + 1);
		refetch(page + 1);
	};

	if (!isLoading && totalPages !== (data?.pages || 1)) {
		setTotalPages(data?.pages || 1);
	}

	// TODO: Only permit linking if there are no translations.
	// TODO: Add footnote under unlink: you must first unlink to link to other translations.

	return (
		<>
			{isModalOpen && (
				<Modal
					title="Link to existing posts:"
					onRequestClose={closeModal}
				>
					<div
						style={{
							display: 'grid',
							flexWrap: 'wrap',
							padding: '20px',
							gap: '8px',
						}}
					>
						<Notice
							status="warning"
							isDismissible={false}
							politeness="polite"
						>
							{' '}
							You will unlink from the post's current translations
							if you link to another.
						</Notice>
						{isLoading && 'Loading...'}
						{isError && 'ERROR!!!'}
						{!isLoading &&
							data?.options &&
							data.options.map((option) => (
								<LinkOption
									{...option}
									postId={postId}
									refetchLangs={refetchLangs}
								/>
							))}
					</div>
					<div
						style={{
							display: 'flex',
							width: '100%',
							paddingLeft: '20px',
						}}
					>
						{page > 1 && (
							<Button variant="secondary" onClick={previousPage}>
								Previous Page
							</Button>
						)}
						<b style={{ padding: '10px' }}>{page}</b>
						{page < totalPages && (
							<Button variant="secondary" onClick={nextPage}>
								Next Page
							</Button>
						)}
					</div>
				</Modal>
			)}
			<Button
				style={{ boxSizing: 'border-box' }}
				variant="secondary"
				onClick={openModal}
			>
				Link translations
			</Button>
		</>
	);
};

export default withLangContext(LinkTranslations);
