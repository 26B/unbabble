import { PluginDocumentSettingPanel } from '@wordpress/edit-post';
import Content from './Content.jsx';

const LanguagePanel = ({ isClassic = false }) => (
	// TODO: move Content logic here.
	<>
		{!isClassic && (
			<PluginDocumentSettingPanel name="ubb-lang-panel" title="Language">
				<Content />
			</PluginDocumentSettingPanel>
		)}
		{isClassic && <Content />}
	</>
);

export default LanguagePanel;
