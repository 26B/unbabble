import { useState, useEffect } from 'react';

import { editPost } from '../services/requests';

const useEditPost = (postId) => {
	const [data, setData] = useState();
	const [isLoading, setIsLoading] = useState(true);
	const [isError, setIsError] = useState(false);

	const fetch = () => {
		if (isNaN(postId)) {
			setIsLoading(false);
			setIsError(false);
			setData([]);
			return;
		}
		setIsLoading(true);
		setIsError(false);
		return editPost(postId)
			.then(({ data }) => setData(data))
			.catch(() => setIsError(true))
			.then(() => setIsLoading(false));
	};

	useEffect(() => {
		fetch();
	}, []);

	return { data: { ...data, postId }, refetch: fetch, isLoading, isError, setIsLoading, setIsError };
};

export default useEditPost;
