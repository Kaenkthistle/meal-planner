<script setup>
import { ref, provide, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useUserStore } from './stores/userStore'

const API_URL = import.meta.env.VITE_API_URL || 'https://cookbook.wasmer.app'

const isDark = ref(false)
const router = useRouter()
const userStore = useUserStore()

provide('isDark', isDark)

function getTheme() {
  isDark.value = !isDark.value
  localStorage.setItem('theme', isDark.value ? 'dark' : 'light')
  document.body.classList.toggle('dark')
}

function goHome() {
  router.push('/')
}

function goToRations() {
  router.push('/rations')
}

function goToAddDish() {
  router.push('/admin/dish/create')
}

async function logout() {
  const token = userStore.token
  if (token) {
    try {
      await fetch(`${API_URL}/api/logout`, {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
      })
    } catch (e) {
      console.error('Ошибка выхода:', e)
    }
  }
  userStore.logout()
  router.push('/catalog')
}

onMounted(() => {
  const saved = localStorage.getItem('theme')
  isDark.value = saved === 'dark'
  if (isDark.value) document.body.classList.add('dark')

  userStore.loadFromStorage()
})
</script>

<template>
  <div>
    <header :class="{ darkItem: isDark }">
      <div class="logo" @click="goHome">
        <span>🍽️ CookBook</span>
      </div>
      <nav class="menuA">
        <router-link to="/about">О ПРОЕКТЕ</router-link>
        <router-link to="/catalog">КАТАЛОГ БЛЮД</router-link>
        <router-link to="/constructor">КОНСТРУКТОР МЕНЮ</router-link>
        <router-link v-if="userStore.isAuthenticated" to="/rations">МОИ РАЦИОНЫ</router-link>
      </nav>
      <div class="right-buttons">
        <router-link v-if="!userStore.isAuthenticated" to="/login" class="login-btn">
          ВОЙТИ
        </router-link>
        <div v-if="userStore.isAuthenticated" class="user-info">
          <span class="user-name">{{ userStore.user?.name }}</span>
          <span v-if="userStore.isAdmin" class="admin-badge">
            <span class="admin-full">Админ</span>
            <span class="admin-short">А</span>
          </span>
          <button @click="logout" class="logout-btn">Выйти</button>
        </div>
        <button class="theme-btn" @click="getTheme" :class="{ darkItem: isDark }">
          {{ isDark ? '☀️' : '🌙' }}
        </button>
      </div>
    </header>
    <main>
      <router-view v-slot="{ Component }">
        <keep-alive include="HomePage">
          <component :is="Component" />
        </keep-alive>
      </router-view>
    </main>

    <!-- Плавающая кнопка добавления блюда (только для админов) -->
    <button 
      v-if="userStore.isAdmin" 
      class="fab-add-dish" 
      @click="goToAddDish"
      :class="{ darkItem: isDark }"
      title="Добавить блюдо"
    >
      <span class="plus-icon">+</span>
      <span class="fab-text">Добавить блюдо</span>
    </button>
  </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700&display=swap');

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

html, body, #app {
  min-height: 100%;
}

body {
  background: hsl(0, 0%, 99%);
  font-family: 'Nunito Sans', sans-serif;
}

/* Шапка - фиксированная */
header {
  background: white;
  width: 100%;
  height: 70px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: fixed;
  top: 0;
  left: 0;
  z-index: 1000;
  box-shadow: 0 2px 10px rgba(136, 136, 136, 0.1);
  padding: 0 5%;
  gap: 20px;
}

/* Логотип */
.logo {
  cursor: pointer;
  font-size: 1.3rem;
  font-weight: 700;
  color: #4C9E2C;
  flex-shrink: 0;
}

.darkItem .logo {
  color: #6abf4a;
}

/* Навигация */
.menuA {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 2rem;
  flex: 1;
}

.menuA a {
  color: #333;
  font-size: 0.95rem;
  text-decoration: none;
  font-weight: 600;
  padding: 8px 12px;
  border-radius: 6px;
  transition: all 0.3s ease;
  white-space: nowrap;
}

.darkItem .menuA a {
  color: white;
}

.menuA a:hover {
  background: rgba(76, 158, 44, 0.1);
  color: #4C9E2C;
}

/* Правая часть */
.right-buttons {
  display: flex;
  align-items: center;
  gap: 15px;
  flex-shrink: 0;
}

.login-btn {
  padding: 8px 16px;
  background: #4C9E2C;
  color: white;
  text-decoration: none;
  border-radius: 6px;
  font-size: 0.9rem;
  font-weight: 600;
  transition: all 0.3s;
}

.login-btn:hover {
  background: #3d7e23;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 10px;
}

.user-name {
  font-size: 0.9rem;
  font-weight: 600;
  color: #333;
}

.darkItem .user-name {
  color: white;
}

.admin-badge {
  background: #ff9800;
  color: white;
  padding: 2px 8px;
  border-radius: 12px;
  font-size: 0.7rem;
  font-weight: 600;
}

.admin-short {
  display: none;
}

.logout-btn {
  padding: 6px 12px;
  background: transparent;
  border: 1px solid #ff4444;
  color: #ff4444;
  border-radius: 6px;
  cursor: pointer;
  font-size: 0.8rem;
  font-weight: 600;
  transition: all 0.3s;
}

.logout-btn:hover {
  background: #ff4444;
  color: white;
}

/* Кнопка темы */
.theme-btn {
  width: 40px;
  height: 40px;
  border: none;
  background: transparent;
  cursor: pointer;
  font-size: 1.2rem;
  border-radius: 50%;
  transition: all 0.3s;
  flex-shrink: 0;
}

.theme-btn:hover {
  background: rgba(136, 136, 136, 0.1);
}

/* Основной контент */
main {
  margin-top: 70px;
  min-height: calc(100vh - 70px);
}

/* Плавающая кнопка добавления блюда */
.fab-add-dish {
  position: fixed;
  bottom: 30px;
  right: 30px;
  background: #4C9E2C;
  color: white;
  border: none;
  border-radius: 50px;
  padding: 14px 24px;
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  font-size: 16px;
  font-weight: 600;
  box-shadow: 0 4px 15px rgba(76, 158, 44, 0.3);
  transition: all 0.3s ease;
  z-index: 999;
}

.fab-add-dish:hover {
  background: #3d7e23;
  transform: translateY(-3px);
  box-shadow: 0 6px 20px rgba(76, 158, 44, 0.4);
}

.fab-add-dish:active {
  transform: translateY(0);
}

.plus-icon {
  font-size: 24px;
  font-weight: bold;
  line-height: 1;
}

.fab-text {
  font-size: 14px;
}

.dark .fab-add-dish {
  background: #3d7e23;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
}

.dark .fab-add-dish:hover {
  background: #2d6a1a;
}

/* Темная тема */
.dark {
  background: hsl(207, 26%, 17%) !important;
  color: white;
  min-height: 100vh;
}

.darkItem {
  background: hsl(209, 23%, 22%);
  color: white;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2) !important;
}

/* === АДАПТИВ ШАПКИ === */

/* Для сверхшироких экранов */
@media (max-width: 1200px) {
  header {
    padding: 0 3%;
  }
  
  .menuA {
    gap: 1.2rem;
  }
  
  .menuA a {
    font-size: 0.85rem;
    padding: 6px 10px;
  }
}

@media (max-width: 1000px) {
  .menuA {
    gap: 0.8rem;
  }
  
  .menuA a {
    font-size: 0.78rem;
    padding: 5px 8px;
  }
}

@media (max-width: 880px) {
  header {
    flex-wrap: wrap;
    height: auto;
    min-height: 70px;
    padding: 10px 20px;
  }
  
  .logo {
    display: none;
  }
  
  .right-buttons {
    order: 1;
    margin-left: auto;
  }
  
  .menuA {
    order: 2;
    width: 100%;
    justify-content: flex-start;
    overflow-x: auto;
    flex-wrap: nowrap;
    gap: 0.8rem;
    padding: 8px 0 4px 0;
  }
  
  .menuA a {
    font-size: 0.75rem;
    padding: 6px 12px;
    white-space: nowrap;
    flex-shrink: 0;
  }
  
  /* Красивый скролл для меню */
  .menuA::-webkit-scrollbar {
    height: 3px;
  }
  
  .menuA::-webkit-scrollbar-track {
    background: rgba(0,0,0,0.1);
    border-radius: 3px;
  }
  
  .menuA::-webkit-scrollbar-thumb {
    background: #4C9E2C;
    border-radius: 3px;
  }
  
  main {
    margin-top: 90px;
  }
}

@media (max-width: 650px) {
  header {
    padding: 8px 15px;
  }
  
  .menuA {
    gap: 0.5rem;
  }
  
  .menuA a {
    font-size: 0.7rem;
    padding: 5px 8px;
  }
  
  .user-name {
    font-size: 0.75rem;
  }
  
  .logout-btn {
    padding: 4px 8px;
    font-size: 0.7rem;
  }
  
  .login-btn {
    padding: 4px 8px;
    font-size: 0.7rem;
  }
  
  .theme-btn {
    width: 32px;
    height: 32px;
    font-size: 1rem;
  }
  
  /* Сжатие бейджа админа - показываем только букву "А" */
  .admin-full {
    display: none;
  }
  
  .admin-short {
    display: inline;
    font-size: 0.7rem;
    font-weight: bold;
  }
  
  .admin-badge {
    padding: 2px 6px;
    min-width: 22px;
    text-align: center;
  }
}

@media (max-width: 500px) {
  .right-buttons {
    gap: 8px;
  }
  
  .user-name {
    display: none;
  }
  
  .menuA {
    gap: 0.3rem;
  }
  
  .menuA a {
    font-size: 0.6rem;
    padding: 4px 6px;
  }
  
  .fab-add-dish {
    bottom: 20px;
    right: 20px;
    padding: 10px 18px;
  }
  
  .fab-text {
    font-size: 12px;
  }
  
  .plus-icon {
    font-size: 20px;
  }
  
  .admin-badge {
    padding: 2px 4px;
    min-width: 18px;
  }
  
  .admin-short {
    font-size: 0.6rem;
  }
}

@media (max-width: 400px) {
  .menuA a {
    font-size: 0.55rem;
    padding: 3px 5px;
  }
  
  .login-btn {
    padding: 3px 6px;
    font-size: 0.65rem;
  }
  
  .logout-btn {
    padding: 3px 6px;
    font-size: 0.6rem;
  }
  
  .theme-btn {
    width: 28px;
    height: 28px;
    font-size: 0.85rem;
  }
}
</style>