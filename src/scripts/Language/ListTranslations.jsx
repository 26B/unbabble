import { withLangContext } from "./contexts/LangContext"

const TranslationRow = ({ translation, isDuplicate }) => {

  return (<tr>
    <td>{translation.language}</td>
    <td><a href={translation.edit}>Edit</a></td>
    <td><a href={translation.view}>View</a></td>
    <td>{ isDuplicate && <b style={{color: "FireBrick"}}>Duplicate</b> }</td>
  </tr>)
}

const ListTranslations = ({ currentLang, translatedLangs, postId }) => (<>
  <p><b>Translations:</b></p>
  {translatedLangs.length > 0 && (
    <table>
      <tbody>
        {translatedLangs.map(
          (translation) => <TranslationRow
            translation={translation}
            postId={postId}
            isDuplicate={ currentLang === translation.language || translatedLangs.filter( ({ ID, language }) => translation.language === language && translation.ID !== ID ).length !== 0 }
            />
        )}
      </tbody>
    </table>
  )}
  {translatedLangs.length === 0 && <p>No translations available.</p>}
</>)

export default withLangContext(ListTranslations)
