export const usePrivateImage = () => {
  const token = useState<string | null>('auth-token')

  const imageUrl = ref<string | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  let objectUrl: string | null = null

  const loadImage = async (photoUrl: string) => {
    if (!token.value) {
      error.value = 'Authentication token not found.'
      return
    }

    loading.value = true
    error.value = null

    try {
      const response = await fetch(photoUrl, {
        headers: {
          Authorization: `Bearer ${token.value}`,
          Accept: 'image/*',
        },
      })

      if (!response.ok) {
        throw new Error(`Failed to load image: ${response.status}`)
      }

      const blob = await response.blob()

      if (!blob.type.startsWith('image/')) {
        throw new Error('Invalid image response.')
      }

      if (objectUrl) {
        URL.revokeObjectURL(objectUrl)
      }

      objectUrl = URL.createObjectURL(blob)
      imageUrl.value = objectUrl
    } catch (err) {
      error.value = err instanceof Error
        ? err.message
        : 'Failed to load image.'

      imageUrl.value = null
    } finally {
      loading.value = false
    }
  }

  const clearImage = () => {
    if (objectUrl) {
      URL.revokeObjectURL(objectUrl)
      objectUrl = null
    }

    imageUrl.value = null
  }

  onScopeDispose(clearImage)

  return {
    imageUrl,
    loading,
    error,
    loadImage,
    clearImage,
  }
}
