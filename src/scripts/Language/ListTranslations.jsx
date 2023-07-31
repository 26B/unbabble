import { getQueryVar } from "../services/searchQuery"
import getUBBSetting from "../services/settings"
import { withLangContext } from "./contexts/LangContext"

const LangRow = ({ language, postId }) => {

  console.log({ language })

  const editUrlQuery = new URLSearchParams({
    post: postId, // TODO: get post
    action: 'edit',
  })
  const editUrl = `${getUBBSetting('admin_url', '')}post.php?${editUrlQuery.toString()}`

  return (<tr>
    <td>{language.name}</td>
    <td><a href={language.edit}>Edit</a></td>
    <td><a href={language.view}>View</a></td>
  </tr>)
}

// TODO:Handle duplicates
const ListTranslations = ({ translatedLangs, postId }) => (<>
  <p><b>Translations:</b></p>
  <table>
    <tbody>
      {translatedLangs.map(
        (language) => <LangRow language={language} postId={postId}/>
      )}
    </tbody>
  </table>
</>)

export default withLangContext(ListTranslations)
