<template>
  <div class="dish-page" :class="{ dark: isDark }">
    <div class="dish-container" :class="{ 'loading-container': isLoading }">
      <!-- Кнопка назад -->
      <button
        class="back-button"
        :class="{ darkItem: isDark }"
        @click="goBack"
      >
        ← Назад
      </button>

      <!-- Кнопка редактирования (только для админов) -->
      <button
        v-if="userStore.isAdmin"
        class="edit-button"
        :class="{ darkItem: isDark }"
        @click="editDish"
        title="Редактировать блюдо"
      >
        ⚙️ Редактировать
      </button>

      <!-- Состояние загрузки -->
      <div v-if="isLoading" class="loading-state">
        <div class="loader"></div>
        <span>Загрузка блюда...</span>
      </div>

      <!-- Контент блюда -->
      <template v-else>
        <!-- Изображение блюда -->
        <div class="dish-image-section">
          <img
            :src="imageSrc"
            :alt="dish.name"
            class="dish-image"
            @error="handleImageError"
          />
        </div>

        <!-- Информация о блюде -->
        <div class="dish-info-section">
          <h1 class="dish-title">{{ dish.name }}</h1>

          <!-- КБЖУ -->
          <div class="nutrition-cards">
            <div
              class="nutrition-card"
              :class="{ darkItem: isDark }"
            >
              <span class="nutrition-value">{{ dish.calories }}</span>
              <span class="nutrition-label">ккал</span>
            </div>

            <div
              class="nutrition-card"
              :class="{ darkItem: isDark }"
            >
              <span class="nutrition-value">{{ dish.proteins }}</span>
              <span class="nutrition-label">белки, г</span>
            </div>

            <div
              class="nutrition-card"
              :class="{ darkItem: isDark }"
            >
              <span class="nutrition-value">{{ dish.fats }}</span>
              <span class="nutrition-label">жиры, г</span>
            </div>

            <div
              class="nutrition-card"
              :class="{ darkItem: isDark }"
            >
              <span class="nutrition-value">{{ dish.carbs }}</span>
              <span class="nutrition-label">углеводы, г</span>
            </div>
          </div>

          <!-- Масса -->
          <div class="dish-mass">
            <span class="mass-value">{{ dish.mass }}</span>
            <span class="mass-unit">г</span>
          </div>

          <!-- Ингредиенты -->
          <div
            v-if="ingredientsList.length > 0"
            class="ingredients-section"
          >
            <h3 class="section-title">Ингредиенты</h3>

            <div class="ingredients-list">
              <div
                v-for="(ingredient, index) in ingredientsList"
                :key="index"
                class="ingredient-item"
                :class="{ darkItem: isDark }"
              >
                <span class="ingredient-name">
                  {{ ingredient.name }}
                </span>

                <span class="ingredient-amount">
                  {{ ingredient.amount }}
                </span>
              </div>
            </div>
          </div>

          <!-- Рецепт приготовления -->
          <div
            v-if="dish.description"
            class="recipe-section"
          >
            <h3 class="section-title">
              Рецепт приготовления
            </h3>

            <p
              class="recipe-text"
              :class="{ darkText: isDark }"
            >
              {{ dish.description }}
            </p>
          </div>

          <!-- Если нет никакой информации -->
          <div
            v-if="ingredientsList.length === 0 && !dish.description"
            class="empty-state"
          >
            <p>Информация о блюде отсутствует</p>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, inject } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useUserStore } from '../stores/userStore'
import axios from 'axios'
import { STORAGE_BASE_URL } from '@/config/api'

const route = useRoute()
const router = useRouter()

const isDark = inject('isDark', ref(false))
const userStore = useUserStore()

const dish = ref({
  id: null,
  name: '',
  mass: 0,
  proteins: 0,
  fats: 0,
  carbs: 0,
  calories: 0,
  description: '',
  ingredients_list: null
})

const ingredientsList = ref([])
const imageSrc = ref('')
const isLoading = ref(true)

const goBack = () => {
  router.back()
}

const editDish = () => {
  router.push(`/admin/dish/edit/${dish.value.id}`)
}

const fetchDish = async () => {
  try {
    isLoading.value = true

    const dishId = route.params.id

    const response = await axios.get(`/api/dishes/${dishId}`)

    dish.value = response.data

    // Обработка ingredients_list
    if (dish.value.ingredients_list) {
      try {
        ingredientsList.value =
          typeof dish.value.ingredients_list === 'string'
            ? JSON.parse(dish.value.ingredients_list)
            : dish.value.ingredients_list
      } catch (e) {
        console.error('Ошибка парсинга ингредиентов:', e)
        ingredientsList.value = []
      }
    } else {
      ingredientsList.value = []
    }

    // Изображение блюда
    imageSrc.value =
      `${STORAGE_BASE_URL}/images/gallery/${dishId}.webp`

  } catch (error) {
    console.error('Ошибка загрузки блюда:', error)
  } finally {
    isLoading.value = false
  }
}

const handleImageError = () => {
  imageSrc.value =
    `${STORAGE_BASE_URL}/images/unknown_dish.webp`
}

onMounted(() => {
  fetchDish()
})
</script>

<style scoped>
.dish-page {
  min-height: calc(100vh - 70px);
  padding: 30px 5%;
  background: hsl(0, 0%, 99%);
}

.dark.dish-page {
  background: hsl(207, 26%, 17%);
}

.dish-container {
  max-width: 1200px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 40px;
  background: white;
  border-radius: 20px;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  position: relative;
}

.dish-container.loading-container {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 500px;
}

.dark .dish-container {
  background: hsl(207, 26%, 22%);
  color: white;
}

.back-button {
  position: absolute;
  top: 20px;
  left: 20px;
  z-index: 10;
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 6px 14px;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
  border: none;
  border-radius: 30px;
  cursor: pointer;
  font-size: 13px;
  font-weight: 500;
  color: white;
  transition: all 0.2s;
}

.back-button.darkItem {
  background: rgba(0, 0, 0, 0.6);
}

.back-button:hover {
  background: #4C9E2C;
  transform: translateX(-2px);
}

.edit-button {
  position: absolute;
  top: 20px;
  right: 20px;
  z-index: 10;
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
  border: none;
  border-radius: 30px;
  cursor: pointer;
  font-size: 13px;
  font-weight: 500;
  color: white;
  transition: all 0.2s;
}

.edit-button.darkItem {
  background: rgba(0, 0, 0, 0.6);
}

.edit-button:hover {
  background: #2196F3;
  transform: translateX(2px);
}

.loading-state {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 16px;
  z-index: 20;
}

.loader {
  width: 48px;
  height: 48px;
  border: 3px solid #e0e0e0;
  border-top-color: #4C9E2C;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

.loading-state span {
  font-size: 14px;
  color: #666;
}

.dark .loading-state span {
  color: #ccc;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.dish-image-section {
  background: #f5f5f5;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 400px;
}

.dark .dish-image-section {
  background: hsl(207, 26%, 17%);
}

.dish-image {
  width: 100%;
  height: 100%;
  max-height: 500px;
  object-fit: cover;
}

.dish-info-section {
  padding: 30px 30px 30px 0;
}

.dish-title {
  font-size: 32px;
  font-weight: 700;
  margin: 0 0 20px 0;
  color: #1a1a1a;
}

.dark .dish-title {
  color: white;
}

.nutrition-cards {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
  margin-bottom: 20px;
}

.nutrition-card {
  background: #f9f9f9;
  border-radius: 12px;
  padding: 12px;
  text-align: center;
  transition: all 0.2s;
}

.nutrition-card.darkItem {
  background: hsl(207, 26%, 28%);
}

.nutrition-value {
  display: block;
  font-size: 24px;
  font-weight: 700;
  color: #4C9E2C;
}

.nutrition-label {
  display: block;
  font-size: 12px;
  color: #666;
  margin-top: 4px;
}

.dark .nutrition-label {
  color: #ccc;
}

.dish-mass {
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 1px solid #e0e0e0;
}

.dark .dish-mass {
  border-bottom-color: rgba(255, 255, 255, 0.1);
}

.mass-value {
  font-size: 18px;
  font-weight: 600;
  color: #4C9E2C;
}

.mass-unit {
  font-size: 14px;
  color: #666;
  margin-left: 4px;
}

.dark .mass-unit {
  color: #ccc;
}

.section-title {
  font-size: 20px;
  font-weight: 600;
  margin: 0 0 15px 0;
  color: #1a1a1a;
  border-left: 4px solid #4C9E2C;
  padding-left: 12px;
}

.dark .section-title {
  color: white;
}

.ingredients-section {
  margin-bottom: 30px;
}

.ingredients-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.ingredient-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 15px;
  background: #f9f9f9;
  border-radius: 8px;
}

.ingredient-item.darkItem {
  background: hsl(207, 26%, 28%);
}

.ingredient-name {
  font-weight: 500;
  font-size: 14px;
}

.dark .ingredient-name {
  color: #e0e0e0;
}

.ingredient-amount {
  font-size: 14px;
  color: #4C9E2C;
  font-weight: 500;
}

.recipe-section {
  margin-bottom: 30px;
}

.recipe-text {
  font-size: 15px;
  line-height: 1.6;
  color: #555;
  margin: 0;
  white-space: pre-line;
}

.dark .recipe-text {
  color: #ddd;
}

.empty-state {
  text-align: center;
  padding: 40px;
  color: #999;
}

.dark .empty-state {
  color: #aaa;
}

@media (max-width: 900px) {
  .dish-container {
    grid-template-columns: 1fr;
    gap: 0;
  }
  
  .dish-info-section {
    padding: 20px;
  }
  
  .nutrition-cards {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .dish-title {
    font-size: 28px;
  }
}

@media (max-width: 768px) {
  .dish-page {
    padding: 20px 15px;
  }
  
  .nutrition-cards {
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
  }
  
  .nutrition-value {
    font-size: 20px;
  }
  
  .dish-title {
    font-size: 24px;
  }
  
  .section-title {
    font-size: 18px;
  }
  
  .back-button,
  .edit-button {
    top: 15px;
    padding: 4px 10px;
    font-size: 11px;
  }
  
  .back-button {
    left: 15px;
  }
  
  .edit-button {
    right: 15px;
  }
  
  .loader {
    width: 40px;
    height: 40px;
  }
}

input:focus, button:focus {
  outline: none;
}
</style>