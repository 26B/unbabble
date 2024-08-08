import { request } from './gateway';

export const editPost = ( id ) =>
	request( {
		url: `/edit/post/${ id }`,
	} );

export const changePostLanguage = ( id, language ) =>
	request( {
		method: 'patch',
		url: `/edit/post/${ id }/language/${ language }`,
	} );

export const copyPost = ( id, lang ) =>
	request( {
		method: 'post',
		url: `/edit/post/${ id }/translation/${ lang }/copy`,
	} );

export const unlinkPost = ( id ) =>
	request( {
		method: 'patch',
		url: `/edit/post/${ id }/translation/unlink`,
	} );

export const linkPost = async ( id, translationId ) =>
	await request( {
		method: 'patch',
		url: `/edit/post/${ id }/translation/${ translationId }/link`,
	} );

export const linkablePosts = ( id, page, search = '' ) =>
	request( {
		url: `/edit/post/${ id }/translation/link?page=${ page }&s=${ search }`,
	} );

export const submitOptions = ( data ) =>
	request( {
		method: 'post',
		url: `/options`,
		data,
	} );

export const updateOptions = () =>
	request( {
		method: 'post',
		url: `/options/update`,
	} );
