import { useState } from 'react';

import { copyPost } from '../services/requests';

const useCopyPost = (postId, language) => {
	const [data, setData] = useState();
	const [isLoading, setIsLoading] = useState(false);
	const [isError, setIsError] = useState(false);

	const mutate = async () => {
		setIsLoading(true);
		return copyPost(postId, language)
			.then((data) => setData(data))
			.catch(() => setIsError(true))
			.then(() => setIsLoading(false));
	};

	return { mutate, data, isLoading, isError };
};

export default useCopyPost;
