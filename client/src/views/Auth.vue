<template>
  <div class="auth-page" :class="{ dark: isDark }">
    <div class="auth-container" :class="{ darkItem: isDark }">
      <!-- Вкладки -->
      <div class="auth-tabs">
        <button 
          :class="['tab-btn', { active: activeTab === 'login' }]"
          @click="activeTab = 'login'"
        >
          Вход
        </button>
        <button 
          :class="['tab-btn', { active: activeTab === 'register' }]"
          @click="activeTab = 'register'"
        >
          Регистрация
        </button>
      </div>

      <!-- Форма входа -->
      <div v-if="activeTab === 'login'" class="auth-form">
        <h2>Вход в аккаунт</h2>
        <p class="subtitle">Введите ваши данные для входа в систему</p>
        
        <form @submit.prevent="handleLogin">
          <div class="form-group">
            <label>Имя пользователя</label>
            <input 
              type="text" 
              v-model="loginForm.name" 
              placeholder="Введите имя пользователя"
              :class="{ darkItem: isDark }"
              required
            />
          </div>
          
          <div class="form-group">
            <label>Пароль</label>
            <div class="password-wrapper">
              <input 
                :type="showLoginPassword ? 'text' : 'password'"
                v-model="loginForm.password" 
                placeholder="Введите пароль"
                :class="{ darkItem: isDark }"
                required
              />
              <button 
                type="button"
                class="password-toggle"
                @click="showLoginPassword = !showLoginPassword"
              >
                {{ showLoginPassword ? '🙈' : '👁️' }}
              </button>
            </div>
          </div>
          
          <div class="form-options">
            <label class="checkbox-label">
              <input type="checkbox" v-model="rememberMe"> Запомнить меня
            </label>
            <a href="javascript:void(0)" class="forgot-link" @click.prevent="forgotPassword">Забыли пароль?</a>
          </div>
          
          <button type="submit" class="submit-btn" :disabled="isLoading">
            {{ isLoading ? 'Вход...' : 'Войти' }}
          </button>
        </form>
        
        <p class="auth-footer">
          Нет аккаунта? 
          <a href="javascript:void(0)" @click.prevent="activeTab = 'register'">Зарегистрируйтесь</a>
        </p>
      </div>

      <!-- Форма регистрации -->
      <div v-if="activeTab === 'register'" class="auth-form">
        <h2>Регистрация</h2>
        <p class="subtitle">Создайте новый аккаунт</p>
        
        <form @submit.prevent="handleRegister">
          <div class="form-group">
            <label>Имя пользователя</label>
            <input 
              type="text" 
              v-model="registerForm.name" 
              placeholder="Придумайте имя пользователя"
              :class="{ darkItem: isDark }"
              required
            />
          </div>
          
          <div class="form-group">
            <label>Пароль</label>
            <div class="password-wrapper">
              <input 
                :type="showRegisterPassword ? 'text' : 'password'"
                v-model="registerForm.password" 
                placeholder="Придумайте пароль"
                :class="{ darkItem: isDark }"
                required
              />
              <button 
                type="button"
                class="password-toggle"
                @click="showRegisterPassword = !showRegisterPassword"
              >
                {{ showRegisterPassword ? '🙈' : '👁️' }}
              </button>
            </div>
          </div>
          
          <div class="form-group">
            <label>Подтверждение пароля</label>
            <div class="password-wrapper">
              <input 
                :type="showConfirmPassword ? 'text' : 'password'"
                v-model="registerForm.password_confirmation" 
                placeholder="Повторите пароль"
                :class="{ darkItem: isDark }"
                required
              />
              <button 
                type="button"
                class="password-toggle"
                @click="showConfirmPassword = !showConfirmPassword"
              >
                {{ showConfirmPassword ? '🙈' : '👁️' }}
              </button>
            </div>
          </div>
          
          <button type="submit" class="submit-btn" :disabled="isLoading">
            {{ isLoading ? 'Регистрация...' : 'Зарегистрироваться' }}
          </button>
        </form>
        
        <p class="auth-footer">
          Уже есть аккаунт? 
          <a href="javascript:void(0)" @click.prevent="activeTab = 'login'">Войдите</a>
        </p>
      </div>

      <!-- Сообщение об ошибке -->
      <div v-if="errorMessage" class="error-message">
        {{ errorMessage }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, inject } from 'vue'
import { useRouter } from 'vue-router'
import { useUserStore } from '../stores/userStore'
import { API_ENDPOINTS } from '@/config/api'

const router = useRouter()
const userStore = useUserStore()
const isDark = inject('isDark', ref(false))

const activeTab = ref('login')
const isLoading = ref(false)
const errorMessage = ref('')
const rememberMe = ref(false)

// Форма входа
const loginForm = ref({
  name: '',
  password: ''
})
const showLoginPassword = ref(false)

// Форма регистрации
const registerForm = ref({
  name: '',
  password: '',
  password_confirmation: ''
})
const showRegisterPassword = ref(false)
const showConfirmPassword = ref(false)

function forgotPassword() {
  alert('Функция восстановления пароля будет добавлена позже')
}

async function handleLogin() {
  isLoading.value = true
  errorMessage.value = ''
  
  try {
    const response = await fetch(API_ENDPOINTS.LOGIN, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(loginForm.value)
    })
    
    const data = await response.json()
    
    if (!response.ok) {
      throw new Error(data.message || 'Ошибка входа')
    }
    
    if (data.data && data.data.user && data.data.access_token) {
      userStore.setUser({
        user: data.data.user,
        access_token: data.data.access_token
      })
    } else if (data.user && data.access_token) {
      userStore.setUser({
        user: data.user,
        access_token: data.access_token
      })
    } else {
      throw new Error('Неверный формат ответа от сервера')
    }
    
    router.push('/catalog')
  } catch (err) {
    errorMessage.value = err.message
    console.error('Ошибка входа:', err)
  } finally {
    isLoading.value = false
  }
}

async function handleRegister() {
  isLoading.value = true
  errorMessage.value = ''
  
  if (registerForm.value.password !== registerForm.value.password_confirmation) {
    errorMessage.value = 'Пароли не совпадают'
    isLoading.value = false
    return
  }
  
  if (registerForm.value.password.length < 6) {
    errorMessage.value = 'Пароль должен содержать минимум 6 символов'
    isLoading.value = false
    return
  }
  
  try {
    const response = await fetch(API_ENDPOINTS.REGISTER, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(registerForm.value)
    })
    
    const data = await response.json()
    
    if (!response.ok) {
      throw new Error(data.message || 'Ошибка регистрации')
    }
    
    if (data.data && data.data.user && data.data.access_token) {
      userStore.setUser({
        user: data.data.user,
        access_token: data.data.access_token
      })
    } else if (data.user && data.access_token) {
      userStore.setUser({
        user: data.user,
        access_token: data.access_token
      })
    } else {
      throw new Error('Неверный формат ответа от сервера')
    }
    
    router.push('/catalog')
  } catch (err) {
    errorMessage.value = err.message
    console.error('Ошибка регистрации:', err)
  } finally {
    isLoading.value = false
  }
}
</script>

<style scoped>
/* Стили остаются без изменений */
.auth-page {
  min-height: calc(100vh - 70px);
  display: flex;
  justify-content: center;
  align-items: center;
  background: #f5f5f5;
  padding: 40px 20px;
}

.dark.auth-page {
  background: hsl(207, 26%, 17%);
}

.auth-container {
  background: white;
  border-radius: 16px;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
  width: 100%;
  max-width: 480px;
  overflow: hidden;
}

.dark .auth-container {
  background: hsl(209, 23%, 22%);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
}

.auth-tabs {
  display: flex;
  border-bottom: 1px solid #eee;
}

.dark .auth-tabs {
  border-bottom-color: rgba(255, 255, 255, 0.1);
}

.tab-btn {
  flex: 1;
  padding: 16px 20px;
  background: none;
  border: none;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  color: #666;
  transition: all 0.3s;
  position: relative;
}

.dark .tab-btn {
  color: #aaa;
}

.tab-btn.active {
  color: #4C9E2C;
}

.tab-btn.active::after {
  content: '';
  position: absolute;
  bottom: -1px;
  left: 0;
  right: 0;
  height: 2px;
  background: #4C9E2C;
}

.tab-btn:hover:not(.active) {
  background: rgba(76, 158, 44, 0.05);
  color: #4C9E2C;
}

.auth-form {
  padding: 32px;
}

.auth-form h2 {
  font-size: 24px;
  font-weight: 700;
  margin-bottom: 8px;
  color: inherit;
  text-align: center;
}

.subtitle {
  text-align: center;
  color: #666;
  font-size: 14px;
  margin-bottom: 28px;
}

.dark .subtitle {
  color: #aaa;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  font-weight: 600;
  font-size: 14px;
  color: #333;
}

.dark .form-group label {
  color: #e0e0e0;
}

.password-wrapper {
  position: relative;
}

.form-group input {
  width: 100%;
  padding: 12px 16px;
  border: 1px solid #ddd;
  border-radius: 8px;
  font-size: 15px;
  transition: all 0.3s;
  background: white;
}

.dark .form-group input {
  background: hsl(207, 26%, 28%);
  border-color: rgba(255, 255, 255, 0.2);
  color: white;
}

.form-group input:focus {
  border-color: #4C9E2C;
  outline: none;
}

.form-group input::placeholder {
  color: #aaa;
  font-size: 14px;
}

.dark .form-group input::placeholder {
  color: #888;
}

.password-toggle {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  cursor: pointer;
  font-size: 18px;
  padding: 0;
  color: #888;
}

.form-options {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
  font-size: 14px;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  color: #555;
}

.dark .checkbox-label {
  color: #ccc;
}

.checkbox-label input {
  width: 16px;
  height: 16px;
  cursor: pointer;
  accent-color: #4C9E2C;
}

.forgot-link {
  color: #4C9E2C;
  text-decoration: none;
  font-size: 14px;
}

.forgot-link:hover {
  text-decoration: underline;
}

.submit-btn {
  width: 100%;
  padding: 14px;
  background: #4C9E2C;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
}

.submit-btn:hover:not(:disabled) {
  background: #3d7e23;
  transform: translateY(-1px);
}

.submit-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.auth-footer {
  text-align: center;
  margin-top: 24px;
  font-size: 14px;
  color: #666;
}

.dark .auth-footer {
  color: #aaa;
}

.auth-footer a {
  color: #4C9E2C;
  text-decoration: none;
  font-weight: 600;
}

.auth-footer a:hover {
  text-decoration: underline;
}

.error-message {
  margin: 0 32px 32px 32px;
  padding: 12px;
  background: rgba(244, 67, 54, 0.1);
  border: 1px solid rgba(244, 67, 54, 0.3);
  border-radius: 8px;
  color: #f44336;
  font-size: 14px;
  text-align: center;
}

@media (max-width: 500px) {
  .auth-form {
    padding: 24px;
  }
  
  .auth-form h2 {
    font-size: 20px;
  }
  
  .tab-btn {
    padding: 12px 16px;
    font-size: 14px;
  }
  
  .form-group input {
    padding: 10px 14px;
    font-size: 14px;
  }
  
  .submit-btn {
    padding: 12px;
    font-size: 14px;
  }
  
  .form-options {
    flex-direction: column;
    gap: 12px;
    align-items: flex-start;
  }
}
</style>