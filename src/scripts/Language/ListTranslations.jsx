import { getQueryVar } from "../services/searchQuery"
import getUBBSetting from "../services/settings"
import { withLangContext } from "./contexts/LangContext"

const LangRow = ({ language, postId, isDuplicate }) => {

  console.log({ language })

  return (<tr>
    <td>{language.name}</td>
    <td><a href={language.edit}>Edit</a></td>
    <td><a href={language.view}>View</a></td>
    <td>{ isDuplicate && <b style={{color: "FireBrick"}}>Duplicate</b> }</td>
  </tr>)
}

const ListTranslations = ({ currentLang, translatedLangs, postId }) => (<>
  <p><b>Translations:</b></p>
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
</>)

export default withLangContext(ListTranslations)
