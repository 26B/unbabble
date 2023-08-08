import { request } from './gateway';

export const editPost = (id) =>
	request({
		url: `/edit/post/${id}`,
	});

export const changePostLanguage = (id, language) =>
	request({
		method: 'patch',
		url: `/edit/post/${id}/language/${language}`,
	});

export const copyPost = (id, lang) =>
	request({
		method: 'post',
		url: `/edit/post/${id}/translation/${lang}/copy`,
	});

export const unlinkPost = (id) =>
	request({
		method: 'patch',
		url: `/edit/post/${id}/translation/unlink`,
	});

export const linkPost = (id, translationId) =>
	request({
		method: 'patch',
		url: `/edit/post/${id}/translation/${translationId}/link`,
	});

export const linkablePosts = (id, page) =>
	request({
		url: `/edit/post/${id}/translation/link?page=${page}`,
	});
