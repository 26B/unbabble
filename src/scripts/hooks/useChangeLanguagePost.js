import { useState } from 'react';

import { changePostLanguage } from '../services/requests';

const useChangeLanguagePost = (postId) => {
	const [isLoading, setIsLoading] = useState(false);
	const [isError, setIsError] = useState(false);

	const mutate = async (language) => {
		setIsLoading(true);
		return changePostLanguage(postId, language)
			.catch(() => setIsError(true))
			.then(() => setIsLoading(false));
	};

	return { mutate, isLoading, isError };
};

export default useChangeLanguagePost;
