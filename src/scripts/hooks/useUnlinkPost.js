import { useState } from 'react';

import { unlinkPost } from '../services/requests';

const useCopyPost = (postId) => {
	const [data, setData] = useState();
	const [isLoading, setIsLoading] = useState(false);
	const [isError, setIsError] = useState(false);

	const mutate = () => {
		setIsLoading(true);
		return unlinkPost(postId)
			.then((data) => setData(data))
			.catch(() => setIsError(true))
			.then(() => setIsLoading(false));
	};

	return { mutate, data, isLoading, isError };
};

export default useCopyPost;
