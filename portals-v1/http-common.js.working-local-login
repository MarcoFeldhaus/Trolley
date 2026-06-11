import axios from 'axios'
import router from './router'
import store from './store/index'
import UserRoles from './enums/UserRoles'

// 'https://backend-php.mycity.cards/'
// 'http://localhost:8000/'
// 'https://dev-backend.mycity.cards/'
const defaultOptions = {
  baseURL: 'http://localhost:8000/',
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json'
  },
  withCredentials: true
}

export const HTTP = axios.create(defaultOptions)

HTTP.interceptors.response.use(function (response) {
  return response
}, function (error) {
  if (error.response.status === 403) {
    const errorMessage = parseError(error, 'Ihre Sitzung ist abgelaufen.')
    let redirectTo = 'Login'
    if (store.state.user.role === UserRoles.PARTNER || store.state.user.role === UserRoles.INTERESTED) {
      redirectTo = 'PartnerLogin'
    } else if (store.state.user.role === UserRoles.EMPLOYER) {
      redirectTo = 'EmployerLogin'
    } else if (store.state.user.role === UserRoles.TROLLEYMAKER) {
      redirectTo = 'TrolleymakerportalLogin'
    }
    store.dispatch('logoutWithPromise').then(() => {
      router.push({ name: redirectTo })
      store.dispatch('addAlert', { category: 'Fehler', message: errorMessage }).then(() => {
      })
    })
  } else if (error.response.status === 503) {
    if (error.response && error.response.data) {
      if (Object.prototype.hasOwnProperty.call(error.response.data, 'type') && error.response.data.type.startsWith('block')) {
        store.dispatch('logoutWithPromise').then(() => {
          router.push({ name: 'Maintenance' })
        })
      } else {
        store.commit('ADD_ALERT', { category: 'Fehler', message: error.response.data.content })
      }
    }
  }
  return Promise.reject(error)
})

function parseError (error, fallbackErrorMessage) {
  if (error && error.response) {
    if (error.response.data.errorMessage && error.response.data.errorMessage !== '') {
      return error.response.data.errorMessage
    } else if (error.response.data.error && error.response.data.error !== '') {
      return error.response.data.error
    }

    if (error.response.data.message && error.response.data.message !== '') {
      return error.response.data.message
    }
  }

  return fallbackErrorMessage
}

export { parseError }
