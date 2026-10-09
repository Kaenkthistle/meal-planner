const API_BASE_URL = import.meta.env.VITE_API_URL || ''
const STORAGE_BASE_URL = import.meta.env.VITE_STORAGE_URL || ''

export const API_ENDPOINTS = {
  LOGIN: `${API_BASE_URL}/login`,
  REGISTER: `${API_BASE_URL}/register`,
  LOGOUT: `${API_BASE_URL}/logout`,
  DISHES: `${API_BASE_URL}/dishes`,
  DISH: (id) => `${API_BASE_URL}/dishes/${id}`,
  FILTERS: `${API_BASE_URL}/filters`,
  RATIONS: `${API_BASE_URL}/rations`,
  RATION: (id) => `${API_BASE_URL}/rations/${id}`,
  GENERATE_DIET: `${API_BASE_URL}/generate-diet`,
  CALCULATE: `${API_BASE_URL}/calculate`,
  MEAL_TIMES: `${API_BASE_URL}/meal-times`,
  MICRONUTRIENTS_CHECK: `${API_BASE_URL}/micronutrients/check`,
}

export { API_BASE_URL, STORAGE_BASE_URL }