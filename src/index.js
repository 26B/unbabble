import React from 'react';
import ReactDOM from 'react-dom';
import { registerPlugin } from '@wordpress/plugins';
import LanguagePanel from './scripts/LanguagePanel';

// Support for classic editor.
window.addEventListener('load', () => {
	const rootEl = document.getElementById('ubb-language');

	if (rootEl) {
		ReactDOM.render(<LanguagePanel isClassic />, rootEl);
	}
});

// Add panel in the new block editor.
registerPlugin('ubb-language-panel', {
	render: LanguagePanel,
	icon: 'airplane',
});
