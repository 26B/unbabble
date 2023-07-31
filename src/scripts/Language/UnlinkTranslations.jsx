import Button from "../components/Button"
import { withLangContext } from "./contexts/LangContext"
import useUnlinkPost from '../hooks/useUnlinkPost'

const UnlinkTranslations = ({ postId, refetchLangs }) => {
  const { mutate, isLoading } = useUnlinkPost(postId) // TODO: Loading and error

  const onClick = () => mutate()
    .then(() => refetchLangs())

  return <Button onClick={onClick} disabled={isLoading}>Unlink from translations</Button>
}

export default withLangContext(UnlinkTranslations)
