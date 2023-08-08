import axios from 'axios';
import { has, merge } from 'lodash';

import getUBBSetting from './settings';

// Keep WP Nonce updated
axios.interceptors.response.use((response) => {
	window.wpApiSettings.nonce = has(response, 'headers.x-wp-nonce')
		? response.headers['x-wp-nonce']
		: window.wpApiSettings.nonce;

	return response;
});

/**
 * Determines if the gateway root is available.
 */
export const hasRoot = () => getUBBSetting('api_root', false) !== false;

/**
 * Returns the URL for the gateway.
 *
 * @return {string} Gateway URL.
 */
export const getRoot = () => getUBBSetting('api_root');

/**
 * Make request to the Gateway.
 *
 * @param {Object} config Request additional configuration.
 * @return {Promise} Promise that should resolve to the gateway response.
 */
export const request = async (config) => {
	if (!hasRoot()) {
		throw new Error('No root available.');
	}

	const defaultConfig = {
		baseURL: getRoot(),
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': window.wpApiSettings.nonce || 0,
		},
	};

	return axios(merge(defaultConfig, config));
};
