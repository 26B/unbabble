import { useState, useEffect } from 'react'

import { editPost } from "../services/requests"

const useEditPost = (postId) => {
  const [data, setData] = useState()
  const [isLoading, setIsLoading] = useState(true)
  const [isError, setIsError] = useState(false)

  const fetch = () => {
    setIsLoading(true)
    return editPost(postId)
      .then(({ data }) => setData(data))
      .catch(() => setIsError(true))
      .then(() => setIsLoading(false))
  }

  useEffect(() => { fetch() }, [postId])

  return { data: { ...data, postId }, refetch: fetch, isLoading, isError }
}

export default useEditPost
