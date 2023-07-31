import { useState, useEffect } from 'react'

import { linkPost } from "../services/requests"

const useLinkPost = (postId, translationPostId) => {
  const [isLoading, setIsLoading] = useState(false)
  const [isSuccess, setIsSuccess] = useState(false)
  const [isError, setIsError] = useState(false)

  const mutate = () => {
    setIsLoading(true)
    setIsSuccess(false)
    return linkPost(postId, translationPostId)
      .then(() => setIsSuccess(true))
      .catch(() => setIsError(true))
      .then(() => setIsLoading(false))
  }

  return { mutate, isSuccess, isLoading, isError }
}

export default useLinkPost
