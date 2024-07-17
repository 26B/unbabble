import React from 'react';
import { createRoot } from 'react-dom/client';
import { registerPlugin } from '@wordpress/plugins';
import LanguagePanel from './scripts/components/LanguagePanel';
import OptionsPage from './scripts/components/OptionsPage';

// Support for classic editor.
window.addEventListener('load', () => {
	const rootEl = document.getElementById('ubb-language');

	if (rootEl) {
		const root = createRoot(rootEl);
		root.render(<LanguagePanel isClassic />);
	}
});

// Add panel in the new block editor.
registerPlugin('ubb-language-panel', {
	render: LanguagePanel,
	icon: 'airplane',
});

// Options page.
window.addEventListener('load', () => {
	const rootEl = document.getElementById('ubb-options-page');

	if (rootEl) {
		const root = createRoot(rootEl);
		root.render(<OptionsPage />);
	}
});
