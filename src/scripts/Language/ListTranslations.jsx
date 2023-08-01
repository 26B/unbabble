import { withLangContext } from "./contexts/LangContext"

const TranslationRow = ({ translation, isDuplicate }) => {

  return (<>
    <span>{translation.language}</span>
    <a href={translation.edit}>Edit</a>
    <a href={translation.view}>View</a>
    { isDuplicate && <b style={{color: "FireBrick"}}>Duplicate</b> }
  </>)
}

const ListTranslations = ({ currentLang, translatedLangs, postId }) => (<>
  <p><b>Translations:</b></p>
  {translatedLangs.length > 0 && (
    translatedLangs.map(
      (translation) => (
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr 2fr', marginBottom: '4px', justifyItems: 'left'}}>
          <TranslationRow
            translation={translation}
            postId={postId}
            isDuplicate={ currentLang === translation.language || translatedLangs.filter( ({ ID, language }) => translation.language === language && translation.ID !== ID ).length !== 0 }
          />
        </div>
      )
    )
  )}
  {translatedLangs.length === 0 && <p>No translations available.</p>}
</>)

export default withLangContext(ListTranslations)
