import { useState } from 'react';

import {
	Button,
	Notice,
	Modal,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalZStack as ZStack,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalVStack as VStack,
} from '@wordpress/components';

import Collapse from '../Collapse';
import useLinkablePosts from '../../hooks/useLinkablePosts';
import { withLangContext } from '../../contexts/LangContext';
import useLinkPost from '../../hooks/useLinkPost';
import Loading from '../Loading';

const LinkOption = ( { postId, refetchLangs, posts, source } ) => {
	const { mutate, isLoading, isSuccess, isError } = useLinkPost(
		postId,
		source
	);

	const onLink = () => mutate().then( () => refetchLangs() );

	const main_post = posts[ 0 ];

	return (
		<div
			style={ {
				display: 'flex',
				justifyContent: 'space-between',
				border: '1px solid #e0e0e0',
				padding: '8px',
			} }
		>
			<div
				style={ {
					display: 'grid',
					gridTemplateColumns: 'repeat(1, 1fr)',
					alignContent: 'center',
				} }
			>
				{ posts.length === 1 && (
					<summary>
						{ '(' + main_post.lang + ') ' + main_post.title }
					</summary>
				) }
				{ posts.length > 1 && (
					<Collapse
						title={ '(' + main_post.lang + ') ' + main_post.title }
					>
						<div
							style={ {
								display: 'flex',
								flexWrap: 'wrap',
								marginLeft: 20,
							} }
						>
							<h4 style={ { marginTop: 0 } }>
								Other translations in the group:
							</h4>
							{ posts.slice( 1 ).map( ( { title, ID, lang } ) => (
								<div
									key={ `link-other-${ ID }` }
									style={ {
										width: '100%',
										justifyContent: 'space-between',
									} }
								>
									<span>({ lang }) </span>
									<span>{ title } </span>
								</div>
							) ) }
						</div>
					</Collapse>
				) }
			</div>
			<Button
				variant="secondary"
				onClick={ onLink }
				disabled={ isLoading || isSuccess }
				style={ { height: 'min-content', margin: 'auto 0 auto 32px' } }
			>
				{ ! isSuccess && 'Link' }
				{ isSuccess && 'Linked' }
			</Button>
		</div>
	);
};

const SearchBar = ( { search, setSearch, refetch, disabled } ) => {
	return (
		<form
			onSubmit={ ( e ) => {
				e.preventDefault();
				refetch();
				return false;
			} }
		>
			<div style={ { display: 'flex', width: '100%' } }>
				<input
					type="text"
					value={ search }
					onChange={ ( e ) => setSearch( e.target.value ) }
					style={ { width: '100%' } }
					disabled={ disabled }
				/>
				<Button
					style={ { marginLeft: '8px' } }
					variant="primary"
					onClick={ () => refetch() }
					disabled={ disabled }
					type="submit"
				>
					Search
				</Button>
			</div>
		</form>
	);
};

const LinkTranslations = ( { postId, refetchLangs } ) => {
	const [ isModalOpen, setIsModalOpen ] = useState( false );
	const { data, refetch, isLoading, isError } = useLinkablePosts( postId, 1, false );
	const [ page, setPage ] = useState( 1 );
	const [ totalPages, setTotalPages ] = useState( data?.pages || 1 );
	const [ search, setSearch ] = useState( '' );

	const openModal = () => {
		if ( isLoading ) {
			refetch( 1 );
		}
		setIsModalOpen( true );
	};

	const closeModal = () => {
		setIsModalOpen( false );
	};
	const previousPage = () => {
		if ( page <= 1 ) {
			return;
		}
		setPage( page - 1 );
		refetch( page - 1, search );
	};

	const nextPage = () => {
		if ( page >= totalPages ) {
			return;
		}
		setPage( page + 1 );
		refetch( page + 1, search );
	};

	const fetchSearch = () => {
		setPage( 1 );
		refetch( 1, search );
	};

	if ( ! isLoading && totalPages !== ( data?.pages || 1 ) ) {
		setTotalPages( data?.pages || 1 );
	}

	// TODO: Only permit linking if there are no translations.
	// TODO: Add footnote under unlink: you must first unlink to link to other translations.

	return (
		<>
			{ isModalOpen && (
				<Modal
					title="Link to existing posts:"
					onRequestClose={ closeModal }
					size="large"
				>
					<div
						style={ {
							display: 'grid',
							flexWrap: 'wrap',
							gap: '8px',
						} }
					>
						<Notice
							status="warning"
							isDismissible={ false }
							politeness="polite"
						>
							{ ' ' }
							You will unlink from the post's current translations
							if you link to another.
						</Notice>
						<SearchBar
							search={ search }
							setSearch={ setSearch }
							refetch={ fetchSearch }
							disabled={ isLoading }
						/>
						<div
							style={ {
								position: 'relative',
								minHeight: '50px',
							} }
						>
							{ data?.options && data.options.length !== 0 && (
								<VStack expanded>
									{ data.options.map( ( option ) => (
										<LinkOption
											key={ `link-option-${ option.source }` }
											{ ...option }
											postId={ postId }
											refetchLangs={ refetchLangs }
										/>
									) ) }
								</VStack>
							) }
							{ isLoading && <Loading overlay /> }
							{ ! isLoading &&
								data?.options &&
								data.options.length === 0 && (
									<div>No results found.</div>
								) }
							{ isError && 'ERROR!!!' }
						</div>
					</div>
					<div
						style={ {
							position: 'sticky',
							display: 'flex',
							justifyContent: 'space-between',
							alignItems: 'center',
							width: '100%',
							marginTop: 20,
						} }
					>
						<Button
							variant="secondary"
							onClick={ previousPage }
							disabled={ page < 2 }
						>
							Previous Page
						</Button>
						<span style={ { display: 'flex', gap: 5 } }>
							<strong>{ page }</strong>
							<span>/</span>
							<span>{ totalPages }</span>
						</span>
						<Button
							variant="secondary"
							onClick={ nextPage }
							disabled={ page >= totalPages }
						>
							Next Page
						</Button>
					</div>
				</Modal>
			) }
			<Button
				style={ { boxSizing: 'border-box' } }
				variant="secondary"
				onClick={ openModal }
			>
				Link translations
			</Button>
		</>
	);
};

export default withLangContext( LinkTranslations );
