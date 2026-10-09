<template>
  <div class="project-info" :class="{ dark: isDark }">
    <div class="container">
      <!-- Hero секция -->
      <div class="hero-section">
        <div class="hero-content">
          <h1 class="hero-title">
            Составьте персональное<br>меню быстро и просто
          </h1>
          <p class="hero-description">
            Выберите цель, укажите данные – наш инструмент автоматически подберёт вам 
            сбалансированный рацион с подсчётом калорий и БЖУ
          </p>
          <button class="hero-button" :class="{ darkItem: isDark }" @click="goToConstructor">
            Перейти к конструктору меню →
          </button>
        </div>
        <div class="hero-image-wrapper">
          <div class="image-circle">
            <img 
              src="/images/tarelka.avif" 
              alt="Тарелка с едой" 
              class="hero-image"
            />
          </div>
        </div>
      </div>

      <!-- Блок для неавторизованных пользователей -->
      <div v-if="!userStore.isAuthenticated" class="auth-alert-section">
        <div class="auth-alert-card" :class="{ darkItem: isDark }">
          <div class="auth-alert-icon">🔐</div>
          <div class="auth-alert-content">
            <h3 class="auth-alert-title">Для возможности сохранения рационов необходима авторизация</h3>
            <p class="auth-alert-description">
              Войдите в свой аккаунт или зарегистрируйтесь, чтобы иметь возможность сохранять составленные рационы
            </p>
            <div class="auth-alert-buttons">
              <button class="auth-login-btn" @click="goToLogin">Войти</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Для авторизованных пользователей - информационный блок -->
      <div v-else class="welcome-section">
        <div class="welcome-card" :class="{ darkItem: isDark }">
          <div class="welcome-icon">🎉</div>
          <div class="welcome-content">
            <h3 class="welcome-title">Добро пожаловать, {{ userStore.user?.name }}!</h3>
            <p class="welcome-description">
              Вы можете создавать рационы, сохранять их и просматривать историю
            </p>
            <button class="welcome-btn" :class="{ darkItem: isDark }" @click="goToRations">
              Мои рационы →
            </button>
          </div>
        </div>
      </div>

      <!-- Преимущества -->
      <div class="features-section">
        <h2 class="features-title">Почему выбирают нас?</h2>
        <div class="features-grid">
          <div class="feature-card" :class="{ darkItem: isDark }">
            <div class="feature-icon">⚖️</div>
            <h3 class="feature-name">Сбалансированный рацион</h3>
            <p class="feature-description">Индивидуальный подбор питательных веществ под ваши цели</p>
          </div>
          <div class="feature-card" :class="{ darkItem: isDark }">
            <div class="feature-icon">🎨</div>
            <h3 class="feature-name">Гибкая настройка</h3>
            <p class="feature-description">Изменяйте рацион по вкусу с автоматической проверкой</p>
          </div>
          <div class="feature-card" :class="{ darkItem: isDark }">
            <div class="feature-icon">📊</div>
            <h3 class="feature-name">Контроль микронутриентов</h3>
            <p class="feature-description">Анализ витаминов и минералов в вашем рационе</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { inject } from 'vue'
import { useRouter } from 'vue-router'
import { useUserStore } from '../stores/userStore'

const router = useRouter()
const isDark = inject('isDark', false)
const userStore = useUserStore()

const goToConstructor = () => {
  router.push('/constructor')
}

const goToLogin = () => {
  router.push('/login')
}

const goToRations = () => {
  router.push('/rations')
}
</script>

<style scoped>
.project-info {
  min-height: calc(100vh - 70px);
  padding: 60px 20px 80px;
  background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
}

.project-info.dark {
  background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
}

.container {
  max-width: 1200px;
  margin: 0 auto;
}

/* Hero секция */
.hero-section {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 60px;
  margin-bottom: 60px;
  flex-wrap: wrap;
}

.hero-content {
  flex: 1;
  min-width: 280px;
}

.hero-title {
  font-size: clamp(32px, 5vw, 56px);
  font-weight: 700;
  line-height: 1.2;
  margin-bottom: 24px;
  color: #1a1a2e;
  background: linear-gradient(135deg, #1a1a2e 0%, #4C9E2C 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.dark .hero-title {
  background: linear-gradient(135deg, #ffffff 0%, #6abf4a 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.hero-description {
  font-size: 18px;
  line-height: 1.6;
  color: #4a5568;
  margin-bottom: 32px;
}

.dark .hero-description {
  color: #cbd5e0;
}

.hero-button {
  background: #4C9E2C;
  color: white;
  border: none;
  border-radius: 40px;
  padding: 14px 32px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 4px 15px rgba(76, 158, 44, 0.3);
}

.hero-button:hover {
  background: #3d7e23;
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(76, 158, 44, 0.4);
}

.hero-image-wrapper {
  flex: 1;
  display: flex;
  justify-content: center;
  min-width: 280px;
}

.image-circle {
  width: 380px;
  height: 380px;
  background: linear-gradient(135deg, #4C9E2C20 0%, #6abf4a20 100%);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  animation: float 3s ease-in-out infinite;
}

@keyframes float {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-20px); }
}

.hero-image {
  width: 280px;
  height: auto;
  filter: drop-shadow(0 10px 20px rgba(0,0,0,0.1));
}

/* Блок для неавторизованных пользователей */
.auth-alert-section {
  margin-bottom: 60px;
  max-width: 800px;
  margin-left: auto;
  margin-right: auto;
}

.auth-alert-card {
  background: linear-gradient(135deg, #fff9e6 0%, #fff3d6 100%);
  border-radius: 20px;
  padding: 32px;
  display: flex;
  align-items: center;
  gap: 24px;
  box-shadow: 0 10px 30px rgba(0,0,0,0.1);
  border: 2px solid #ffd700;
}

.dark .auth-alert-card {
  background: linear-gradient(135deg, #2a2a1e 0%, #1e1e16 100%);
  border-color: #ffd700;
}

.auth-alert-icon {
  font-size: 64px;
  flex-shrink: 0;
}

.auth-alert-content {
  flex: 1;
}

.auth-alert-title {
  font-size: 20px;
  font-weight: 700;
  margin-bottom: 8px;
  color: #1a1a2e;
}

.dark .auth-alert-title {
  color: #ffffff;
}

.auth-alert-description {
  font-size: 14px;
  color: #4a5568;
  margin-bottom: 20px;
}

.dark .auth-alert-description {
  color: #cbd5e0;
}

.auth-alert-buttons {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
}

.auth-login-btn {
  background: #4C9E2C;
  color: white;
  border: none;
  border-radius: 30px;
  padding: 10px 24px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
}

.auth-login-btn:hover {
  background: #3d7e23;
  transform: translateY(-2px);
}

.auth-register-btn {
  background: transparent;
  border: 2px solid #4C9E2C;
  color: #4C9E2C;
  border-radius: 30px;
  padding: 8px 22px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
}

.auth-register-btn:hover {
  background: rgba(76, 158, 44, 0.1);
  transform: translateY(-2px);
}

.dark .auth-register-btn {
  border-color: #6abf4a;
  color: #6abf4a;
}

.dark .auth-register-btn:hover {
  background: rgba(106, 191, 74, 0.2);
}

/* Блок для авторизованных пользователей */
.welcome-section {
  margin-bottom: 60px;
  max-width: 800px;
  margin-left: auto;
  margin-right: auto;
}

.welcome-card {
  background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
  border-radius: 20px;
  padding: 32px;
  display: flex;
  align-items: center;
  gap: 24px;
  box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.dark .welcome-card {
  background: linear-gradient(135deg, #1e3a1e 0%, #162e16 100%);
}

.welcome-icon {
  font-size: 64px;
  flex-shrink: 0;
}

.welcome-content {
  flex: 1;
}

.welcome-title {
  font-size: 20px;
  font-weight: 700;
  margin-bottom: 8px;
  color: #1a1a2e;
}

.dark .welcome-title {
  color: #ffffff;
}

.welcome-description {
  font-size: 14px;
  color: #4a5568;
  margin-bottom: 20px;
}

.dark .welcome-description {
  color: #cbd5e0;
}

.welcome-btn {
  background: #4C9E2C;
  color: white;
  border: none;
  border-radius: 30px;
  padding: 10px 24px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
}

.welcome-btn:hover {
  background: #3d7e23;
  transform: translateY(-2px);
}

/* Секция преимуществ */
.features-section {
  margin-bottom: 0;
}

.features-title {
  text-align: center;
  font-size: 36px;
  font-weight: 700;
  margin-bottom: 48px;
  color: #1a1a2e;
}

.dark .features-title {
  color: #ffffff;
}

.features-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 30px;
}

.feature-card {
  background: white;
  border-radius: 20px;
  padding: 32px 24px;
  text-align: center;
  transition: all 0.3s ease;
  box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}

.feature-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 20px 40px rgba(0,0,0,0.1);
}

.feature-card.darkItem {
  background: hsl(207, 26%, 22%);
  border: 1px solid rgba(255,255,255,0.1);
}

.feature-icon {
  font-size: 48px;
  margin-bottom: 20px;
}

.feature-name {
  font-size: 20px;
  font-weight: 700;
  margin-bottom: 12px;
  color: #1a1a2e;
}

.dark .feature-name {
  color: #ffffff;
}

.feature-description {
  font-size: 14px;
  line-height: 1.5;
  color: #4a5568;
}

.dark .feature-description {
  color: #cbd5e0;
}

/* Адаптив */
@media (max-width: 968px) {
  .hero-section {
    flex-direction: column;
    text-align: center;
    gap: 40px;
    margin-bottom: 60px;
  }
  
  .hero-title {
    font-size: 36px;
  }
  
  .hero-description {
    font-size: 16px;
  }
  
  .image-circle {
    width: 280px;
    height: 280px;
  }
  
  .hero-image {
    width: 200px;
  }
  
  .features-title {
    font-size: 28px;
  }
  
  .auth-alert-card,
  .welcome-card {
    flex-direction: column;
    text-align: center;
  }
  
  .auth-alert-buttons {
    justify-content: center;
  }
}

@media (max-width: 768px) {
  .project-info {
    padding: 40px 16px 60px;
  }
  
  .features-grid {
    grid-template-columns: 1fr;
    gap: 20px;
  }
  
  .hero-button {
    width: 100%;
    max-width: 280px;
  }
}

@media (max-width: 480px) {
  .hero-title {
    font-size: 28px;
  }
  
  .hero-description {
    font-size: 14px;
  }
  
  .features-title {
    font-size: 24px;
  }
  
  .image-circle {
    width: 220px;
    height: 220px;
  }
  
  .hero-image {
    width: 160px;
  }
  
  .auth-alert-icon,
  .welcome-icon {
    font-size: 48px;
  }
  
  .auth-alert-title,
  .welcome-title {
    font-size: 18px;
  }
  
  .auth-alert-buttons {
    flex-direction: column;
    gap: 10px;
  }
  
  .auth-login-btn,
  .auth-register-btn,
  .welcome-btn {
    width: 100%;
  }
}
</style>