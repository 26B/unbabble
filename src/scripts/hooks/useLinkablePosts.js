import { useState, useEffect } from 'react'

import { linkablePosts } from "../services/requests"

const useLinkablePosts = (postId) => {
  const [data, setData] = useState()
  const [isLoading, setIsLoading] = useState(true)
  const [isError, setIsError] = useState(false)

  useEffect(() => {
    setIsLoading(true)
    linkablePosts(postId)
      .then(({ data }) => setData(data))
      .catch(() => setIsError(true))
      .then(() => setIsLoading(false))
  }, [postId])

  return { data, isLoading, isError }
}

export default useLinkablePosts
