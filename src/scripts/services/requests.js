import { request } from "./gateway";

export const editPost = (id) => request({
  url: `/edit/post/${id}`,
})

export const copyPost = (id, lang) => request({
  method: 'post',
  url: `/edit/post/${id}/translation/${lang}/copy`
})

export const unlinkPost = (id) => request({
  method: 'post',
  url: `/edit/post/${id}/translation/unlink`
})
