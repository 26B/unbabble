import { useState } from 'react';

import Languages from './Languages.jsx';
import Routing from './Routing.jsx';
import Types from './Types.jsx';

import { Flex, Button } from '@wordpress/components';
import getUBBSetting from '../../services/settings';

import { submitOptions } from '../../services/requests';

const OptionsPage = ({}) => {
	const [languages, setLanguages] = useState(
		getUBBSetting('options', [])?.allowed_languages.map((language) => {
			return {
				language,
				hidden: getUBBSetting(
					'options',
					[]
				)?.hidden_languages?.includes(language),
			};
		})
	);
	const [defaultLanguage, setDefaultLanguage] = useState(
		getUBBSetting('options', [])?.default_language
	);

	const [routing, setRouting] = useState({
		router: getUBBSetting('options', [])?.router,
		router_options: getUBBSetting('options', [])?.router_options,
	});

	const [postTypes, setPostTypes] = useState(
		getUBBSetting('options', [])?.post_types
	);

	const [taxonomies, setTaxonomies] = useState(
		getUBBSetting('options', [])?.taxonomies
	);

	const submit = () => {
		submitOptions({
			languages,
			defaultLanguage,
			routing,
			postTypes,
			taxonomies,
		});
	};

	return (
		<>
			<form action="options.php" method="post">
				<Flex
					direction="row"
					style={{ width: '100%', justifyContent: 'normal' }}
				>
					<h1>Unbabble Settings</h1>
					<Button className="button button-primary" onClick={submit}>
						Save
					</Button>
				</Flex>
				<Languages
					languages={languages}
					setLanguages={setLanguages}
					defaultLanguage={defaultLanguage}
					setDefaultLanguage={setDefaultLanguage}
				/>
				<Routing
					languages={languages}
					defaultLanguage={defaultLanguage}
					routing={routing}
					setRouting={setRouting}
				/>
				<Types
					title="Post Types"
					addLabel="Add post type"
					selectLabel="Select a post type"
					addSelectedLabel="Add selected post type"
					types={postTypes}
					setTypes={setPostTypes}
					allTypes={getUBBSetting('wpPostTypes', [])}
				/>
				<Types
					title="Taxonomies"
					addLabel="Add taxonomy"
					selectLabel="Select a taxonomy"
					addSelectedLabel="Add selected taxonomy"
					types={taxonomies}
					setTypes={setTaxonomies}
					allTypes={getUBBSetting('wpTaxonomies', [])}
				/>
				<Button
					className="button button-primary"
					style={{ marginTop: 24 }}
					onClick={submit}
				>
					Save
				</Button>
			</form>
		</>
	);
};

export default OptionsPage;
