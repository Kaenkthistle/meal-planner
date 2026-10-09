<template>
  <div class="admin-dish-form" :class="{ dark: isDark }">
    <div class="container">
      <h1>{{ isEditMode ? 'Редактирование блюда' : 'Добавление нового блюда' }}</h1>
      
      <div v-if="isLoading" class="loading-state">
        <div class="loader"></div>
        <span>Загрузка данных...</span>
      </div>
      
      <form v-else @submit.prevent="submitForm" class="dish-form" :class="{ darkItem: isDark }" novalidate>
        <div class="form-grid">
          <div class="form-group" :class="{ 'has-error': errors.name }">
            <label>Название блюда *</label>
            <input type="text" v-model="form.name" :class="{ darkItem: isDark, 'error-input': errors.name }" />
            <span class="error-message" v-if="errors.name">{{ errors.name }}</span>
          </div>
          
          <div class="form-group" :class="{ 'has-error': errors.dish_type_id }">
            <label>Тип блюда *</label>
            <select v-model="form.dish_type_id" :class="{ darkItem: isDark, 'error-input': errors.dish_type_id }">
              <option value="">Выберите тип</option>
              <option v-for="type in dishTypes" :key="type.id" :value="type.id">
                {{ type.name }}
              </option>
            </select>
            <span class="error-message" v-if="errors.dish_type_id">{{ errors.dish_type_id }}</span>
          </div>
          
          <div class="form-group" :class="{ 'has-error': errors.proteins }">
            <label>Белки (на 100г) *</label>
            <input type="number" step="0.1" min="0" max="100" v-model="form.proteins" :class="{ darkItem: isDark, 'error-input': errors.proteins }" />
            <span class="error-message" v-if="errors.proteins">{{ errors.proteins }}</span>
          </div>
          
          <div class="form-group" :class="{ 'has-error': errors.fats }">
            <label>Жиры (на 100г) *</label>
            <input type="number" step="0.1" min="0" max="100" v-model="form.fats" :class="{ darkItem: isDark, 'error-input': errors.fats }" />
            <span class="error-message" v-if="errors.fats">{{ errors.fats }}</span>
          </div>
          
          <div class="form-group" :class="{ 'has-error': errors.carbs }">
            <label>Углеводы (на 100г) *</label>
            <input type="number" step="0.1" min="0" max="100" v-model="form.carbs" :class="{ darkItem: isDark, 'error-input': errors.carbs }" />
            <span class="error-message" v-if="errors.carbs">{{ errors.carbs }}</span>
          </div>
          
          <div class="form-group" :class="{ 'has-error': errors.calories }">
            <label>Калории (на 100г) *</label>
            <input type="number" step="1" min="0" max="1000" v-model="form.calories" :class="{ darkItem: isDark, 'error-input': errors.calories }" />
            <span class="error-message" v-if="errors.calories">{{ errors.calories }}</span>
          </div>
          
          <div class="form-group" :class="{ 'has-error': errors.mass }">
            <label>Масса порции (г) *</label>
            <input type="number" step="1" min="1" max="5000" v-model="form.mass" :class="{ darkItem: isDark, 'error-input': errors.mass }" />
            <span class="error-message" v-if="errors.mass">{{ errors.mass }}</span>
          </div>
          
          <div class="form-group" :class="{ 'has-error': errors.portion_type }">
            <label>Тип порции *</label>
            <select v-model="form.portion_type" :class="{ darkItem: isDark, 'error-input': errors.portion_type }">
              <option value="scalable">Масштабируемая</option>
              <option value="whole">Цельная</option>
              <option value="fixed">Фиксированная</option>
            </select>
            <span class="error-message" v-if="errors.portion_type">{{ errors.portion_type }}</span>
          </div>
          
          <div class="form-group" :class="{ 'has-error': errors.max_portions }">
            <label>Макс. число блюд в порции *</label>
            <input type="number" step="1" min="1" max="255" v-model="form.max_portions" :class="{ darkItem: isDark, 'error-input': errors.max_portions }" />
            <span class="error-message" v-if="errors.max_portions">{{ errors.max_portions }}</span>
          </div>
        </div>
        
        <div class="form-group full-width">
          <label>Рецепт приготовления</label>
          <textarea rows="4" v-model="form.description" placeholder="Опишите процесс приготовления..." :class="{ darkItem: isDark }"></textarea>
        </div>
        
        <div class="form-group full-width">
          <label>Ингредиенты (с количеством)</label>
          <div class="ingredients-list-editor">
            <div v-for="(ing, index) in ingredientsList" :key="index" class="ingredient-row">
              <input 
                type="text" 
                v-model="ing.name" 
                placeholder="Название ингредиента" 
                :class="{ darkItem: isDark }"
                class="ingredient-name-input"
              />
              <input 
                type="text" 
                v-model="ing.amount" 
                placeholder="Количество (например: 180 г)" 
                :class="{ darkItem: isDark }"
                class="ingredient-amount-input"
              />
              <button type="button" @click="removeIngredient(index)" class="remove-ingredient-btn" title="Удалить">✕</button>
            </div>
            <button type="button" @click="addIngredient" class="add-ingredient-btn" :class="{ darkItem: isDark }">
              + Добавить ингредиент
            </button>
          </div>
        </div>
        
        <div class="form-group full-width">
          <label>Приёмы пищи</label>
          <div class="checkbox-group">
            <label class="checkbox-label">
              <input type="checkbox" value="1" v-model="form.meal_time_ids" />
              Завтрак
            </label>
            <label class="checkbox-label">
              <input type="checkbox" value="2" v-model="form.meal_time_ids" />
              Обед
            </label>
            <label class="checkbox-label">
              <input type="checkbox" value="3" v-model="form.meal_time_ids" />
              Перекус
            </label>
            <label class="checkbox-label">
              <input type="checkbox" value="4" v-model="form.meal_time_ids" />
              Ужин
            </label>
          </div>
        </div>
        
        <div class="form-group full-width">
          <label>Ингредиенты (из каталога)</label>
          <div class="ingredients-tree-container">
            <div class="ingredients-search">
              <input 
                type="text" 
                v-model="ingredientSearch" 
                placeholder="🔍 Поиск ингредиентов..." 
                :class="{ darkItem: isDark }"
                class="search-input"
              />
            </div>
            <div class="ingredients-tree">
              <div v-for="node in filteredIngredientsTree" :key="node.id" class="ingredient-group">
                <div class="ingredient-group-header" :class="{ expanded: expandedGroups[node.id] }">
                  <button 
                    type="button"
                    class="expand-toggle"
                    @click="toggleGroup(node.id)"
                    v-if="node.children && node.children.length"
                  >
                    {{ expandedGroups[node.id] ? '▼' : '▶' }}
                  </button>
                  <span v-else class="expand-toggle-placeholder"></span>
                  <label class="parent-label">
                    <input type="checkbox" :value="node.id" v-model="form.ingredient_ids" />
                    <span class="ingredient-name">{{ node.name }}</span>
                    <span v-if="node.children && node.children.length" class="child-count">({{ node.children.length }})</span>
                  </label>
                </div>
                <div v-if="node.children && node.children.length && expandedGroups[node.id]" class="ingredient-children">
                  <div v-for="child in node.children" :key="child.id" class="ingredient-child-item">
                    <label class="child-label">
                      <input type="checkbox" :value="child.id" v-model="form.ingredient_ids" />
                      <span class="ingredient-name">{{ child.name }}</span>
                    </label>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="selected-ingredients-hint" v-if="form.ingredient_ids.length > 0">
            <span>✅ Выбрано ингредиентов: {{ form.ingredient_ids.length }}</span>
          </div>
        </div>
        
        <div class="form-group full-width">
          <label>Микронутриенты</label>
          <div class="micronutrients-list">
            <div v-for="(nutrient, idx) in micronutrients" :key="nutrient.micronutrient_id" class="micronutrient-item">
              <span class="nutrient-name">{{ nutrient.name }}</span>
              <input 
                type="number" 
                step="0.01" 
                min="0"
                :placeholder="`${nutrient.unit}`" 
                v-model="micronutrientValues[idx]"
                class="nutrient-input" 
                :class="{ darkItem: isDark }" 
              />
            </div>
          </div>
        </div>
        
        <div class="form-group full-width">
          <label>Изображение</label>
          <input type="file" @change="handleImageUpload" accept="image/*" :class="{ darkItem: isDark }" />
          <div v-if="imagePreview" class="image-preview">
            <img :src="imagePreview" alt="Preview" />
            <button v-if="isEditMode && currentImageUrl" type="button" @click="removeImage" class="remove-image-btn">Удалить фото</button>
          </div>
          <div v-if="isEditMode && currentImageUrl && !imagePreview" class="current-image">
            <img :src="currentImageUrl" alt="Current" />
            <button type="button" @click="removeImage" class="remove-image-btn">Удалить фото</button>
          </div>
        </div>
        
        <div class="form-actions">
          <button v-if="isEditMode" type="button" @click="confirmDelete" class="delete-btn">🗑️ Удалить блюдо</button>
          <div class="action-buttons">
            <button type="button" @click="$router.back()" class="cancel-btn">Отмена</button>
            <button type="submit" :disabled="isSubmitting" class="submit-btn">
              {{ isSubmitting ? 'Сохранение...' : (isEditMode ? 'Обновить блюдо' : 'Сохранить блюдо') }}
            </button>
          </div>
        </div>
      </form>
    </div>

    <!-- Модальное окно подтверждения удаления -->
    <div v-if="showDeleteModal" class="modal-overlay" @click.self="closeDeleteModal">
      <div class="modal-content" :class="{ darkItem: isDark }">
        <h3>Удалить блюдо?</h3>
        <p>Вы уверены, что хотите удалить блюдо <strong>"{{ form.name }}"</strong>?</p>
        <p class="warning">Это действие нельзя отменить. Все связанные данные будут удалены.</p>
        <div class="modal-buttons">
          <button @click="closeDeleteModal" class="cancel-btn">Отмена</button>
          <button @click="deleteDish" class="confirm-delete-btn">Удалить</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, inject, nextTick } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useUserStore } from '../stores/userStore'
import axios from 'axios'

const router = useRouter()
const route = useRoute()
const userStore = useUserStore()
const isDark = inject('isDark', ref(false))

const isEditMode = computed(() => !!route.params.id)
const dishId = computed(() => route.params.id)

const isSubmitting = ref(false)
const isLoading = ref(false)
const isDeleting = ref(false)
const showDeleteModal = ref(false)
const imagePreview = ref(null)
const imageFile = ref(null)
const ingredientSearch = ref('')
const expandedGroups = ref({})
const currentImageUrl = ref(null)

const micronutrientValues = ref([])

// Объект для хранения ошибок валидации
const errors = ref({})

const form = ref({
  name: '',
  proteins: '',
  fats: '',
  carbs: '',
  calories: '',
  mass: '',
  description: '',
  dish_type_id: '',
  portion_type: 'scalable',
  max_portions: 1,
  meal_time_ids: [],
  ingredient_ids: []
})

const ingredientsList = ref([{ name: '', amount: '' }])
const dishTypes = ref([])
const ingredientsTree = ref([])
const micronutrients = ref([])

// Функция валидации формы
function validateForm() {
  errors.value = {}
  let isValid = true
  let firstErrorField = null
  
  if (!form.value.name || form.value.name.trim() === '') {
    errors.value.name = 'Введите название блюда'
    if (!firstErrorField) firstErrorField = 'name'
    isValid = false
  }
  
  if (!form.value.dish_type_id) {
    errors.value.dish_type_id = 'Выберите тип блюда'
    if (!firstErrorField) firstErrorField = 'dish_type_id'
    isValid = false
  }
  
  const proteins = parseFloat(form.value.proteins)
  if (isNaN(proteins) || proteins < 0 || proteins > 100) {
    errors.value.proteins = 'Введите корректное значение (0-100)'
    if (!firstErrorField) firstErrorField = 'proteins'
    isValid = false
  }
  
  const fats = parseFloat(form.value.fats)
  if (isNaN(fats) || fats < 0 || fats > 100) {
    errors.value.fats = 'Введите корректное значение (0-100)'
    if (!firstErrorField) firstErrorField = 'fats'
    isValid = false
  }
  
  const carbs = parseFloat(form.value.carbs)
  if (isNaN(carbs) || carbs < 0 || carbs > 100) {
    errors.value.carbs = 'Введите корректное значение (0-100)'
    if (!firstErrorField) firstErrorField = 'carbs'
    isValid = false
  }
  
  const calories = parseFloat(form.value.calories)
  if (isNaN(calories) || calories < 0) {
    errors.value.calories = 'Введите корректное значение (0-1000)'
    if (!firstErrorField) firstErrorField = 'calories'
    isValid = false
  }
  
  const mass = parseFloat(form.value.mass)
  if (isNaN(mass) || mass < 1) {
    errors.value.mass = 'Введите корректную массу (минимум 1 г)'
    if (!firstErrorField) firstErrorField = 'mass'
    isValid = false
  }
  
  if (!form.value.portion_type) {
    errors.value.portion_type = 'Выберите тип порции'
    if (!firstErrorField) firstErrorField = 'portion_type'
    isValid = false
  }
  
  const maxPortions = parseInt(form.value.max_portions)
  if (isNaN(maxPortions) || maxPortions < 1) {
    errors.value.max_portions = 'Введите корректное значение (минимум 1)'
    if (!firstErrorField) firstErrorField = 'max_portions'
    isValid = false
  }
  
  // Плавный скролл к первому полю с ошибкой
  if (!isValid && firstErrorField) {
    nextTick(() => {
      const errorElement = document.querySelector(`[data-field="${firstErrorField}"]`) || 
                           document.querySelector(`.form-group.has-error`)
      if (errorElement) {
        errorElement.scrollIntoView({ behavior: 'smooth', block: 'center' })
      }
    })
  }
  
  return isValid
}

const filteredIngredientsTree = computed(() => {
  if (!ingredientSearch.value.trim()) {
    return ingredientsTree.value
  }
  
  const searchTerm = ingredientSearch.value.toLowerCase()
  
  function filterNode(node) {
    const matchesNode = node.name.toLowerCase().includes(searchTerm)
    
    let filteredChildren = []
    if (node.children && node.children.length) {
      filteredChildren = node.children.filter(child => 
        child.name.toLowerCase().includes(searchTerm)
      )
    }
    
    if (matchesNode || filteredChildren.length > 0) {
      if (searchTerm && filteredChildren.length > 0) {
        expandedGroups.value[node.id] = true
      }
      return {
        ...node,
        children: filteredChildren
      }
    }
    return null
  }
  
  return ingredientsTree.value.map(filterNode).filter(node => node !== null)
})

function addIngredient() {
  ingredientsList.value.push({ name: '', amount: '' })
}

function removeIngredient(index) {
  ingredientsList.value.splice(index, 1)
}

function toggleGroup(groupId) {
  expandedGroups.value[groupId] = !expandedGroups.value[groupId]
}

function initMicronutrientsValues() {
  micronutrientValues.value = new Array(micronutrients.value.length).fill('')
}

function loadMicronutrientsFromDish(savedMicronutrients) {
  if (!savedMicronutrients || savedMicronutrients.length === 0) {
    initMicronutrientsValues()
    return
  }
  
  for (let i = 0; i < micronutrients.value.length; i++) {
    const nutrient = micronutrients.value[i]
    const saved = savedMicronutrients.find(s => s.micronutrient_id === nutrient.micronutrient_id)
    if (saved) {
      micronutrientValues.value[i] = saved.mass
    }
  }
}

async function fetchFilters() {
  try {
    const response = await axios.get('/api/filters')
    const data = response.data
    dishTypes.value = data.dish_types
    ingredientsTree.value = data.ingredients
    
    micronutrients.value = data.micronutrients.map(n => ({
      micronutrient_id: n.id,
      name: n.name,
      unit: n.unit || ''
    }))
    
    initMicronutrientsValues()
    
    ingredientsTree.value.forEach(node => {
      expandedGroups.value[node.id] = false
    })
  } catch (error) {
    console.error('Ошибка загрузки фильтров:', error)
  }
}

async function fetchDish() {
  if (!isEditMode.value) return
  
  isLoading.value = true
  try {
    const token = userStore.token
    const response = await axios.get(`/api/dishes/${dishId.value}`, {
      headers: {
        'Authorization': `Bearer ${token}`
      }
    })
    
    const dish = response.data
    
    const proteinsPer100g = Math.round((dish.proteins * 100) / dish.mass * 10) / 10
    const fatsPer100g = Math.round((dish.fats * 100) / dish.mass * 10) / 10
    const carbsPer100g = Math.round((dish.carbs * 100) / dish.mass * 10) / 10
    const caloriesPer100g = Math.round((dish.calories * 100) / dish.mass)
    
    form.value = {
      name: dish.name,
      proteins: proteinsPer100g,
      fats: fatsPer100g,
      carbs: carbsPer100g,
      calories: caloriesPer100g,
      mass: dish.mass,
      description: dish.description || '',
      dish_type_id: dish.dish_type_id,
      portion_type: dish.portion_type,
      max_portions: dish.max_portions,
      meal_time_ids: dish.meal_times?.map(mt => mt.meal_time_id) || [],
      ingredient_ids: dish.ingredients?.map(i => i.ingredient_id) || []
    }
    
    if (dish.ingredients_list && Array.isArray(dish.ingredients_list)) {
      ingredientsList.value = dish.ingredients_list.map(ing => ({
        name: ing.name || '',
        amount: ing.amount || ''
      }))
      if (ingredientsList.value.length === 0) {
        ingredientsList.value = [{ name: '', amount: '' }]
      }
    }
    
    if (dish.micronutrients && Array.isArray(dish.micronutrients)) {
      loadMicronutrientsFromDish(dish.micronutrients)
    } else {
      initMicronutrientsValues()
    }
    
    if (dish.image_url) {
      currentImageUrl.value = dish.image_url
    }
    
  } catch (error) {
    console.error('Ошибка загрузки блюда:', error)
    alert('Не удалось загрузить данные блюда')
    router.push('/catalog')
  } finally {
    isLoading.value = false
  }
}

function handleImageUpload(event) {
  const file = event.target.files[0]
  if (file) {
    imageFile.value = file
    const reader = new FileReader()
    reader.onload = (e) => {
      imagePreview.value = e.target.result
    }
    reader.readAsDataURL(file)
  }
}

async function removeImage() {
  if (confirm('Удалить изображение?')) {
    imageFile.value = null
    imagePreview.value = null
    currentImageUrl.value = null
  }
}

async function submitForm() {
  // Очищаем старые ошибки
  errors.value = {}
  
  // Кастомная валидация
  if (!validateForm()) {
    return
  }
  
  // Дополнительная валидация на отрицательные числа
  if (form.value.proteins < 0) form.value.proteins = 0
  if (form.value.fats < 0) form.value.fats = 0
  if (form.value.carbs < 0) form.value.carbs = 0
  if (form.value.calories < 0) form.value.calories = 0
  if (form.value.mass < 1) form.value.mass = 1
  if (form.value.max_portions < 1) form.value.max_portions = 1
  
  isSubmitting.value = true
  
  const formData = new FormData()
  formData.append('name', form.value.name)
  formData.append('proteins', form.value.proteins)
  formData.append('fats', form.value.fats)
  formData.append('carbs', form.value.carbs)
  formData.append('calories', form.value.calories)
  formData.append('mass', form.value.mass)
  formData.append('description', form.value.description || '')
  formData.append('dish_type_id', form.value.dish_type_id)
  formData.append('portion_type', form.value.portion_type)
  formData.append('max_portions', form.value.max_portions)
  
  form.value.meal_time_ids.forEach(id => formData.append('meal_time_ids[]', id))
  form.value.ingredient_ids.forEach(id => formData.append('ingredient_ids[]', id))
  
  const validIngredients = ingredientsList.value.filter(ing => ing.name.trim() && ing.amount.trim())
  if (validIngredients.length > 0) {
    const ingredientsArray = validIngredients.map(ing => ({
      name: ing.name.trim(),
      amount: ing.amount.trim()
    }))
    formData.append('ingredients', JSON.stringify(ingredientsArray))
  }
  
  const micronutrientsArray = []
  for (let i = 0; i < micronutrients.value.length; i++) {
    const value = micronutrientValues.value[i]
    if (value && parseFloat(value) > 0) {
      micronutrientsArray.push({
        micronutrient_id: micronutrients.value[i].micronutrient_id,
        mass: parseFloat(value)
      })
    }
  }
  
  if (micronutrientsArray.length > 0) {
    formData.append('micronutrients', JSON.stringify(micronutrientsArray))
  }
  
  if (imageFile.value) {
    formData.append('image', imageFile.value)
  }
  
  if (isEditMode.value && !currentImageUrl.value && !imageFile.value) {
    formData.append('remove_image', 'true')
  }
  
  try {
    const token = userStore.token
    const url = isEditMode.value ? `/api/dishes/${dishId.value}` : '/api/dishes'
    
    const response = await axios.post(url, formData, {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'multipart/form-data'
      }
    })
    
    if (response.data.success) {
      const successMessage = isEditMode.value ? 'Блюдо успешно обновлено!' : 'Блюдо успешно добавлено!'
      alert(successMessage)
      
      const newDishId = isEditMode.value ? dishId.value : response.data.data?.dish_id
      if (newDishId) {
        router.push(`/dish/${newDishId}`)
      } else {
        router.push('/catalog')
      }
    } else {
      throw new Error(response.data.message || 'Ошибка сохранения')
    }
  } catch (error) {
    console.error('Ошибка:', error.response?.data || error.message)
    const errorMsg = error.response?.data?.message || error.message || 'Произошла ошибка'
    alert('Ошибка: ' + errorMsg)
  } finally {
    isSubmitting.value = false
  }
}

function confirmDelete() {
  showDeleteModal.value = true
}

function closeDeleteModal() {
  showDeleteModal.value = false
}

async function deleteDish() {
  isDeleting.value = true
  try {
    const token = userStore.token
    const response = await axios.delete(`/api/dishes/${dishId.value}`, {
      headers: {
        'Authorization': `Bearer ${token}`
      }
    })
    
    if (response.data.success) {
      alert('Блюдо успешно удалено!')
      router.push('/catalog')
    } else {
      throw new Error(response.data.message || 'Ошибка удаления')
    }
  } catch (error) {
    console.error('Ошибка:', error)
    alert('Ошибка при удалении блюда: ' + (error.response?.data?.message || error.message))
  } finally {
    isDeleting.value = false
    closeDeleteModal()
  }
}

onMounted(async () => {
  if (!userStore.isAuthenticated) {
    router.push('/auth')
    return
  }
  if (!userStore.isAdmin) {
    router.push('/catalog')
    return
  }
  await fetchFilters()
  await fetchDish()
})
</script>

<style scoped>
.admin-dish-form {
  min-height: calc(100vh - 70px);
  padding: 30px 5%;
}

.container {
  max-width: 900px;
  margin: 0 auto;
}

h1 {
  font-size: 28px;
  margin-bottom: 30px;
  color: #333;
}

.dark h1 {
  color: white;
}

.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px;
  gap: 16px;
}

.loader {
  width: 48px;
  height: 48px;
  border: 3px solid #e0e0e0;
  border-top-color: #4C9E2C;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.dish-form {
  background: white;
  padding: 30px;
  border-radius: 16px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.dark .dish-form {
  background: hsl(209, 23%, 22%);
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20px;
}

.form-group {
  margin-bottom: 20px;
}

.form-group.full-width {
  grid-column: span 2;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  font-weight: 600;
  font-size: 14px;
  color: #555;
}

.dark .form-group label {
  color: #e0e0e0;
}

.form-group input,
.form-group select,
.form-group textarea {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #ddd;
  border-radius: 8px;
  font-size: 14px;
  transition: all 0.3s;
}

.dark .form-group input,
.dark .form-group select,
.dark .form-group textarea {
  background: hsl(207, 26%, 28%);
  border-color: rgba(255,255,255,0.2);
  color: white;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  border-color: #4C9E2C;
  outline: none;
}

/* Стили для ошибок */
.form-group.has-error label {
  color: #f44336;
}

.error-input {
  border-color: #f44336 !important;
  background-color: rgba(244, 67, 54, 0.05) !important;
}

.dark .error-input {
  background-color: rgba(244, 67, 54, 0.15) !important;
}

.error-message {
  display: block;
  color: #f44336;
  font-size: 12px;
  margin-top: 5px;
}

.ingredients-list-editor {
  border: 1px solid #ddd;
  border-radius: 8px;
  padding: 12px;
  background: #fafafa;
}

.dark .ingredients-list-editor {
  border-color: rgba(255,255,255,0.2);
  background: hsl(207, 26%, 25%);
}

.ingredient-row {
  display: flex;
  gap: 10px;
  margin-bottom: 10px;
  align-items: center;
}

.ingredient-name-input {
  flex: 2;
  padding: 8px 12px;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 14px;
}

.ingredient-amount-input {
  flex: 1;
  padding: 8px 12px;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 14px;
}

.remove-ingredient-btn {
  width: 32px;
  height: 32px;
  border-radius: 6px;
  background: #ff4444;
  color: white;
  border: none;
  cursor: pointer;
  font-size: 14px;
  transition: all 0.2s;
}

.remove-ingredient-btn:hover {
  background: #cc0000;
}

.add-ingredient-btn {
  width: 100%;
  padding: 8px;
  background: #4C9E2C;
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 13px;
  transition: all 0.2s;
  margin-top: 8px;
}

.add-ingredient-btn:hover {
  background: #3d7e23;
}

.checkbox-group {
  display: flex;
  gap: 15px;
  flex-wrap: wrap;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  font-size: 14px;
}

.ingredients-tree-container {
  border: 1px solid #ddd;
  border-radius: 8px;
  overflow: hidden;
}

.dark .ingredients-tree-container {
  border-color: rgba(255,255,255,0.2);
}

.ingredients-search {
  padding: 12px;
  border-bottom: 1px solid #ddd;
  background: #fafafa;
}

.dark .ingredients-search {
  border-bottom-color: rgba(255,255,255,0.1);
  background: hsl(207, 26%, 25%);
}

.search-input {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 14px;
}

.dark .search-input {
  background: hsl(207, 26%, 28%);
  border-color: rgba(255,255,255,0.2);
  color: white;
}

.ingredients-tree {
  max-height: 350px;
  overflow-y: auto;
  padding: 12px;
}

.ingredient-group {
  margin-bottom: 8px;
}

.ingredient-group-header {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 8px;
  border-radius: 6px;
  transition: background 0.2s;
}

.ingredient-group-header:hover {
  background: rgba(76, 158, 44, 0.1);
}

.expand-toggle {
  width: 20px;
  height: 20px;
  background: none;
  border: none;
  cursor: pointer;
  font-size: 10px;
  color: #666;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  flex-shrink: 0;
}

.dark .expand-toggle {
  color: #aaa;
}

.expand-toggle-placeholder {
  width: 20px;
  flex-shrink: 0;
}

.parent-label {
  display: flex;
  align-items: center;
  cursor: pointer;
  flex: 1;
  padding: 2px 0;
}

.parent-label input[type="checkbox"] {
  width: 18px;
  height: 18px;
  margin: 0;
  margin-right: 16px;
  cursor: pointer;
  flex-shrink: 0;
}

.ingredient-name {
  font-size: 14px;
  line-height: 1.3;
}

.child-count {
  font-size: 11px;
  color: #888;
  font-weight: normal;
  margin-left: 4px;
}

.dark .child-count {
  color: #aaa;
}

.ingredient-children {
  margin-left: 32px;
  margin-top: 4px;
  margin-bottom: 4px;
}

.ingredient-child-item {
  padding: 4px 8px;
  border-radius: 4px;
  transition: background 0.2s;
}

.ingredient-child-item:hover {
  background: rgba(76, 158, 44, 0.08);
}

.child-label {
  display: flex;
  align-items: center;
  cursor: pointer;
  padding: 2px 0;
}

.child-label input[type="checkbox"] {
  width: 18px;
  height: 18px;
  margin: 0;
  margin-right: 16px;
  cursor: pointer;
  flex-shrink: 0;
}

.selected-ingredients-hint {
  margin-top: 8px;
  padding: 6px 12px;
  background: rgba(76, 158, 44, 0.1);
  border-radius: 6px;
  font-size: 12px;
  color: #4C9E2C;
}

.dark .selected-ingredients-hint {
  background: rgba(106, 191, 74, 0.15);
  color: #6abf4a;
}

.micronutrients-list {
  max-height: 250px;
  overflow-y: auto;
  border: 1px solid #ddd;
  border-radius: 8px;
  padding: 12px;
}

.dark .micronutrients-list {
  border-color: rgba(255,255,255,0.2);
}

.micronutrient-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 0;
  border-bottom: 1px solid #eee;
  gap: 20px;
}

.dark .micronutrient-item {
  border-bottom-color: rgba(255,255,255,0.1);
}

.nutrient-name {
  font-size: 14px;
  flex: 1;
}

.nutrient-input {
  width: 100px;
  padding: 6px 10px;
  border: 1px solid #ddd;
  border-radius: 6px;
}

.image-preview, .current-image {
  margin-top: 12px;
  position: relative;
}

.image-preview img, .current-image img {
  max-width: 200px;
  max-height: 150px;
  border-radius: 8px;
}

.remove-image-btn {
  margin-top: 8px;
  padding: 4px 12px;
  background: #ff4444;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 12px;
}

.remove-image-btn:hover {
  background: #cc0000;
}

.form-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 30px;
  padding-top: 20px;
  border-top: 1px solid #eee;
}

.dark .form-actions {
  border-top-color: rgba(255,255,255,0.1);
}

.action-buttons {
  display: flex;
  gap: 15px;
}

.delete-btn {
  padding: 10px 20px;
  background: #f44336;
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.3s;
}

.delete-btn:hover {
  background: #d32f2f;
  transform: translateY(-1px);
}

.cancel-btn {
  padding: 10px 20px;
  background: #e0e0e0;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.3s;
}

.dark .cancel-btn {
  background: hsl(207, 26%, 28%);
  color: white;
}

.cancel-btn:hover {
  background: #ccc;
}

.dark .cancel-btn:hover {
  background: hsl(207, 26%, 35%);
}

.submit-btn {
  padding: 10px 24px;
  background: #4C9E2C;
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
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

/* Модальное окно */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 2000;
  backdrop-filter: blur(4px);
}

.modal-content {
  background: white;
  border-radius: 16px;
  padding: 28px;
  max-width: 400px;
  width: 90%;
  text-align: center;
}

.dark .modal-content {
  background: hsl(209, 23%, 22%);
  color: white;
}

.modal-content h3 {
  font-size: 20px;
  margin-bottom: 12px;
}

.modal-content p {
  margin-bottom: 16px;
  color: #666;
}

.dark .modal-content p {
  color: #aaa;
}

.warning {
  color: #f44336 !important;
  font-size: 13px;
}

.modal-buttons {
  display: flex;
  gap: 12px;
  justify-content: center;
  margin-top: 20px;
}

.confirm-delete-btn {
  padding: 10px 20px;
  background: #f44336;
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.3s;
}

.confirm-delete-btn:hover {
  background: #d32f2f;
}

@media (max-width: 768px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
  
  .form-group.full-width {
    grid-column: span 1;
  }
  
  .admin-dish-form {
    padding: 20px;
  }
  
  .dish-form {
    padding: 20px;
  }
  
  .ingredient-row {
    flex-wrap: wrap;
  }
  
  .ingredient-name-input,
  .ingredient-amount-input {
    flex: 100%;
    min-width: 100%;
  }
  
  .micronutrient-item {
    flex-wrap: wrap;
    gap: 10px;
  }
  
  .nutrient-input {
    width: 100%;
  }
  
  .ingredient-children {
    margin-left: 20px;
  }
  
  .parent-label input[type="checkbox"],
  .child-label input[type="checkbox"] {
    margin-right: 12px;
  }
  
  .form-actions {
    flex-direction: column;
    gap: 10px;
  }
  
  .action-buttons {
    width: 100%;
  }
  
  .delete-btn {
    width: 100%;
  }
  
  .submit-btn,
  .cancel-btn {
    flex: 1;
  }
}
</style>