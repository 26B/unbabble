import { withLangContext } from "./contexts/LangContext"

const LangRow = ({ language, isDuplicate }) => {

  return (<tr>
    <td>{language.name}</td>
    <td><a href={language.edit}>Edit</a></td>
    <td><a href={language.view}>View</a></td>
    <td>{ isDuplicate && <b style={{color: "FireBrick"}}>Duplicate</b> }</td>
  </tr>)
}

const ListTranslations = ({ currentLang, translatedLangs, postId }) => (<>
  <p><b>Translations:</b></p>
  {translatedLangs.length > 0 && (
    <table>
      <tbody>
        {translatedLangs.map(
          (language) => <LangRow
            language={language}
            postId={postId}
            isDuplicate={translatedLangs.filter( ({ name }) => language.name === name || currentLang === name )}
            />
        )}
      </tbody>
    </table>
  )}
  {translatedLangs.length === 0 && <p>No translations available.</p>}
</>)

export default withLangContext(ListTranslations)
