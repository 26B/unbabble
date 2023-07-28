import Collapse from '../components/Collapse'

const CreateTranslations = () => {
  const langs = [ 'en_US', 'ar_AR', 'fr_FR' ]

  return (
    <Collapse title="Create Translations">
      <table>
        <tbody>
          {langs.map(
            (lang) => (<tr>
              <th>{lang}:</th>
              <td><a href="">Create</a></td>
              <td>/</td>
              <td><a href="">Copy</a></td>
            </tr>)
          )}
        </tbody>
      </table>
    </Collapse>
  )
}

export default CreateTranslations
