import { withLangContext } from '../../contexts/LangContext';
import { Flex, PanelRow, Tooltip } from '@wordpress/components';

const TranslationRow = ({ translation, isDuplicate, languagesInfo }) => {
	const languageLabel = `${
		languagesInfo[translation.language].native_name
	} (${translation.language})`;

	return (
		<>
			<span style={{ gridColumn: '1/2' }}>
				<span>{languageLabel}</span>
				{isDuplicate && (
					<span style={{ marginLeft: '4px' }}>
						<Tooltip
							text="Another post with the same language already exists in the translation group."
							delay="500"
						>
							<span
								className="dashicons dashicons-warning"
								style={{ color: 'FireBrick' }}
							></span>
						</Tooltip>
					</span>
				)}
			</span>
			<span style={{ gridColumn: '2/2', gap: '10px', display: 'flex' }}>
				<a href={translation.edit}>Edit</a>
				<a href={translation.view}>View</a>
			</span>
		</>
	);
};

const ListTranslations = ({
	currentLang,
	translatedLangs,
	postId,
	languagesInfo,
}) => (
	<PanelRow style={{ flexDirection: 'column' }}>
		<Flex direction="column" style={{ width: '100%' }}>
			<span
				style={{
					textTransform: 'uppercase',
					fontSize: 11,
					fontWeight: 500,
				}}
			>
				translations
			</span>
			{translatedLangs.length > 0 &&
				translatedLangs.map((translation) => (
					<div
						key={`ubb-link-translation-${translation.language}`}
						style={{
							display: 'grid',
							marginBottom: 4,
							justifyContent: 'space-between',
						}}
					>
						<TranslationRow
							translation={translation}
							postId={postId}
							isDuplicate={
								currentLang === translation.language ||
								translatedLangs.filter(
									({ ID, language }) =>
										translation.language === language &&
										translation.ID !== ID
								).length !== 0
							}
							languagesInfo={languagesInfo}
						/>
					</div>
				))}
			{translatedLangs.length === 0 && <p>No translations available.</p>}
		</Flex>
	</PanelRow>
);

export default withLangContext(ListTranslations);
