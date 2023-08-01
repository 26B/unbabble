import { withLangContext } from "./contexts/LangContext"

const LangRow = ({ language, isDuplicate }) => {

  return (<>
    <span>{language.name}</span>
    <a href={language.edit}>Edit</a>
    <a href={language.view}>View</a>
    { isDuplicate && <b style={{color: "FireBrick"}}>Duplicate</b> }
  </>)
}

const ListTranslations = ({ currentLang, translatedLangs, postId }) => (<>
  <p><b>Translations:</b></p>
  {translatedLangs.length > 0 && (
    translatedLangs.map(
      (language) => (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', marginBottom: '4px', justifyItems: 'center'}}>
          <LangRow
            language={language}
            postId={postId}
            isDuplicate={translatedLangs.filter( ({ name }) => language.name === name || currentLang === name )}
          />
        </div>
      )
    )
  )}
  {translatedLangs.length === 0 && <p>No translations available.</p>}
</>)

export default withLangContext(ListTranslations)
