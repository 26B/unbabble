import { useState, useEffect } from 'react';

import { linkablePosts } from '../services/requests';

const useLinkablePosts = (postId, page) => {
	const [data, setData] = useState();
	const [isLoading, setIsLoading] = useState(true);
	const [isError, setIsError] = useState(false);

	const fetch = async (page) => {
		setIsLoading(true);
		return linkablePosts(postId, page)
			.then(({ data }) => setData(data))
			.catch(() => setIsError(true))
			.then(() => setIsLoading(false));
	};

	useEffect(() => {
		fetch(page);
	}, [postId, page]);

	return { data, refetch: fetch, isLoading, isError };
};

export default useLinkablePosts;
