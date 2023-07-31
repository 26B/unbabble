import { createContext } from 'react'

export const LangContext = createContext({
  currentLang: '',
  postId: '',
  languages: [],
  translations: [],
  translatedLangs: [],
  untranslatedLangs: [],
  refetchLangs: () => {},
})

export const withLangContext = WrappedComponent => props => (
  <LangContext.Consumer>
    {context => <WrappedComponent {...props} {...context}/>}
  </LangContext.Consumer>
)

export default LangContext
