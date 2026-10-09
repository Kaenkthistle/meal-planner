<template>
  <div class="constructor-page" :class="{ dark: isDark }">
    <!-- Индикатор загрузки рациона -->
    <div v-if="isLoadingRation" class="loading-overlay">
      <div class="loading-spinner"></div>
      <p>Загрузка рациона...</p>
    </div>

    <!-- Форма сверху по центру -->
    <div class="form-wrapper">
      <div class="form-section" :class="{ darkItem: isDark }">
        <h2 class="section-title">Заполните ваши параметры для расчёта суточной нормы килокалорий и питательных веществ</h2>
        
        <div class="params-table">
          <div class="table-row">
            <div class="table-label">Пол</div>
            <div class="table-value">
              <div class="gender-buttons">
                <button 
                  :class="['gender-btn', { active: form.gender === 'female' }]"
                  @click="form.gender = 'female'"
                >Женский</button>
                <button 
                  :class="['gender-btn', { active: form.gender === 'male' }]"
                  @click="form.gender = 'male'"
                >Мужской</button>
              </div>
            </div>
            <div class="table-label">Рост</div>
            <div class="table-value">
              <input type="number" v-model.number="form.height" class="param-input" />
              <span class="param-unit">см</span>
            </div>
            <div class="table-label">Вес</div>
            <div class="table-value">
              <input type="number" v-model.number="form.weight" class="param-input" />
              <span class="param-unit">кг</span>
            </div>
          </div>
          
          <div class="table-row activity-row">
            <div class="table-label">Цель</div>
            <div class="table-value">
              <select v-model="form.goal" class="param-select">
                <option value="lose">Похудение</option>
                <option value="maintain">Поддержание веса</option>
                <option value="gain">Набор массы</option>
              </select>
            </div>
            <div class="table-label">Уровень физ. активности</div>
            <div class="table-value activity-value">
              <select v-model="form.activity_level" class="param-select">
                <option value="low">Низкий</option>
                <option value="medium">Средний</option>
                <option value="high">Высокий</option>
              </select>
            </div>
            <div class="table-label">Возраст</div>
            <div class="table-value">
              <input type="number" v-model.number="form.age" class="param-input" />
              <span class="param-unit">лет</span>
            </div>
          </div>
        </div>
        
        <button @click="calculateNorm" class="calculate-btn" :class="{ darkItem: isDark }" :disabled="isCalculating">
          {{ isCalculating ? 'Расчёт...' : 'Рассчитать' }}
        </button>
      </div>
    </div>

    <!-- Основной контент с результатами -->
    <div class="results-wrapper" v-if="targetKbju">
      <div class="norms-block" :class="{ darkItem: isDark }">
        <h3>Ваша суточная норма</h3>
        <div class="norms-grid">
          <div class="norm-item">Белки: {{ round(targetKbju.proteins) }} г.</div>
          <div class="norm-item">Жиры: {{ round(targetKbju.fats) }} г.</div>
          <div class="norm-item">Углеводы: {{ round(targetKbju.carbohydrates) }} г.</div>
          <div class="norm-item">Ккал: {{ round(targetKbju.calories) }}</div>
        </div>
      </div>
      
      <div class="diet-block" :class="{ darkItem: isDark }" v-if="displayedDiet">
        <div class="diet-header">
          <div class="actual-norms">
            <h3>В составленном рационе</h3>
            <div class="actual-grid">
              <div class="actual-item" :class="getNutrientClass('proteins')">
                Белки: {{ round(currentTotals.p) }} / {{ round(targetKbju.proteins) }} г.
              </div>
              <div class="actual-item" :class="getNutrientClass('fats')">
                Жиры: {{ round(currentTotals.f) }} / {{ round(targetKbju.fats) }} г.
              </div>
              <div class="actual-item" :class="getNutrientClass('carbs')">
                Углеводы: {{ round(currentTotals.c) }} / {{ round(targetKbju.carbohydrates) }} г.
              </div>
              <div class="actual-item" :class="getNutrientClass('calories')">
                Ккал: {{ round(currentTotals.k) }} / {{ round(targetKbju.calories) }}
              </div>
            </div>
          </div>
          
          <div v-if="editingRationId && currentRationName" class="ration-title-section" :class="{ darkItem: isDark }">
            <div class="ration-title-label">📋 Текущий рацион:</div>
            <div v-if="!isEditingName" class="ration-title-display">
              <span class="ration-title-name">{{ currentRationName }}</span>
              <button class="edit-name-btn" @click="startEditName" title="Переименовать">✏️</button>
            </div>
            <div v-else class="ration-title-edit">
              <input 
                type="text" 
                v-model="editNameValue" 
                class="ration-title-input"
                :class="{ darkItem: isDark }"
                maxlength="150"
                @keyup.enter="saveNewName"
                @keyup.esc="cancelEditName"
                autofocus
              />
              <button class="save-name-btn" @click="saveNewName" title="Сохранить">💾</button>
              <button class="cancel-name-btn" @click="cancelEditName" title="Отмена">✕</button>
            </div>
          </div>
          
          <div class="diet-controls">
            <div class="left-controls">
              <a href="#" class="link" @click.prevent="openIngredientsModal">⚙️ Управление ингредиентами</a>
              <a 
                href="#" 
                class="link" 
                :class="{ 'disabled-link': !hasActiveDishes }"
                @click.prevent="!hasActiveDishes ? null : openMicronutrientsModal()"
              >
                📊 Рекомендации по микронутриентам
              </a>
              <a href="#" class="link clear-link" @click.prevent="clearDiet">🗑️ Очистить рацион</a>
            </div>
            <div class="right-controls">
              <div class="meals-count-control">
                <span>Число приёмов пищи:</span>
                <div class="count-buttons">
                  <button 
                    :class="['count-btn', { active: plannedMealsCount === 3 }]"
                    @click="setPlannedMealsCount(3)"
                  >3</button>
                  <button 
                    :class="['count-btn', { active: plannedMealsCount === 4 }]"
                    @click="setPlannedMealsCount(4)"
                  >4</button>
                </div>
              </div>
              <button 
                v-if="userStore.isAuthenticated"
                @click="openSaveModal" 
                class="save-btn" 
                :class="{ darkItem: isDark, disabled: !hasActiveDishes }"
                :disabled="!hasActiveDishes || isSaving"
              >
                {{ isSaving ? 'Сохранение...' : (editingRationId ? '💾 Сохранить изменения' : '💾 Сохранить рацион') }}
              </button>
              <button @click="regenerateDiet" class="update-btn" :class="{ darkItem: isDark }" :disabled="isGenerating">
                {{ isGenerating ? 'Генерация...' : 'Сгенерировать рацион' }}
              </button>
            </div>
          </div>
        </div>
        
        <div class="meals-list">
          <div 
            v-for="meal in sortedMeals" 
            :key="meal.meal_time_id" 
            class="meal-section"
          >
            <div class="meal-header">
              <div class="meal-title-wrapper">
                <h3 class="meal-title">{{ getMealTitle(meal.meal_time_id) }}</h3>
                <button 
                  v-if="meal.meal_time_id !== 1 && meal.meal_time_id !== 2 && meal.meal_time_id !== 4"
                  class="remove-meal-btn" 
                  :class="{ darkItem: isDark }"
                  @click="removeMeal(meal.meal_time_id)"
                  title="Удалить приём пищи"
                >
                  🗑️
                </button>
              </div>
              <button 
                class="add-dish-btn" 
                :class="{ darkItem: isDark }"
                @click="openDishSelector(meal.meal_time_id, getMealTitle(meal.meal_time_id))"
                title="Добавить блюдо"
              >
                + Добавить блюдо
              </button>
            </div>
            <div class="items-grid">
              <div v-if="meal.items.length === 0" class="empty-meal-message" :class="{ darkItem: isDark }">
                Нет блюд. Нажмите "+ Добавить блюдо"
              </div>
              <div v-for="item in meal.items" :key="item.uniqueId" class="card-with-controls">
                <Card
                  :id="item.id"
                  :name="item.name"
                  :proteins="round(item.displayProteins)"
                  :fats="round(item.displayFats)"
                  :carbs="round(item.displayCarbs)"
                  :mass="round(item.displayMass)"
                  :calories="round(item.displayCalories)"
                  @click="goToDish(item.id)"
                />
                
                <div v-if="item.portion_type === 'scalable'" class="scale-controls">
                  <button 
                    class="scale-btn minus" 
                    :class="{ darkItem: isDark }"
                    @click="updateMultiplier(item.uniqueId, -0.05)"
                    :disabled="item.multiplier <= 0.7"
                  >−</button>
                  <span class="multiplier-badge" :class="{ darkItem: isDark }">
                    {{ (item.multiplier * 100).toFixed(0) }}%
                  </span>
                  <button 
                    class="scale-btn plus" 
                    :class="{ darkItem: isDark }"
                    @click="updateMultiplier(item.uniqueId, 0.05)"
                    :disabled="item.multiplier >= 1.25"
                  >+</button>
                </div>
                
                <button 
                  class="exclude-btn" 
                  :class="{ darkItem: isDark }"
                  @click="excludeItem(item.uniqueId)"
                  title="Исключить блюдо"
                >
                  ✕
                </button>
              </div>
            </div>
          </div>
        </div>
        
        <button 
          v-if="displayedDiet && sortedMeals.length < 5" 
          class="add-meal-btn" 
          :class="{ darkItem: isDark }"
          @click="addNewMeal"
        >
          + Добавить приём пищи
        </button>
      </div>
    </div>

    <MicronutrientsModal 
      :visible="showMicronutrientsModal"
      :is-dark="isDark"
      :dishes="currentDishesForCheck"
      @close="showMicronutrientsModal = false"
    />

    <IngredientsModal 
      :visible="showIngredientsModal"
      :is-dark="isDark"
      :current-excluded="excludedIngredients"
      @close="showIngredientsModal = false"
      @save="saveExcludedIngredients"
    />

    <SaveRationModal 
      :visible="showSaveModal"
      :is-dark="isDark"
      :current-name="currentRationName"
      :totals="currentTotals"
      :total-dishes="totalDishesCount"
      @close="showSaveModal = false"
      @save="saveRation"
    />
  </div>
</template>

<script setup>
import { ref, computed, inject, onMounted, watch, onBeforeUnmount } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import Card from '@/components/Card.vue'
import MicronutrientsModal from '@/components/Micronutrients.vue'
import IngredientsModal from '@/components/Ingredients.vue'
import SaveRationModal from '@/components/SaveRationModal.vue'
import { useUserStore } from '../stores/userStore'
import { API_ENDPOINTS } from '@/config/api'

const router = useRouter()
const route = useRoute()
const isDark = inject('isDark', ref(false))
const userStore = useUserStore()

const showMicronutrientsModal = ref(false)
const showIngredientsModal = ref(false)
const showSaveModal = ref(false)
const excludedIngredients = ref([])
const isLoadingRation = ref(false)
const isSaving = ref(false)
const currentRationName = ref('')
const editingRationId = ref(null)
const isEditingName = ref(false)
const editNameValue = ref('')

const plannedMealsCount = ref(4)

// Для защиты от потери изменений
const hasUnsavedChanges = ref(false)
const savedStateHash = ref('')
const isInternalOperation = ref(false)

const getCurrentStateHash = () => {
  if (!originalDiet.value) return ''
  
  const dishes = []
  for (const meal of originalDiet.value.meals) {
    for (const item of meal.items) {
      if (!excludedItems.value.has(item.uniqueId)) {
        dishes.push({
          id: item.id,
          multiplier: Math.round((multipliers.value.get(item.uniqueId) || item.multiplier || 1) * 100)
        })
      }
    }
  }
  dishes.sort((a, b) => a.id - b.id)
  return JSON.stringify(dishes)
}

const checkUnsavedChanges = () => {
  if (isInternalOperation.value) return
  const currentHash = getCurrentStateHash()
  hasUnsavedChanges.value = currentHash !== savedStateHash.value && savedStateHash.value !== ''
}

const markAsSaved = () => {
  savedStateHash.value = getCurrentStateHash()
  hasUnsavedChanges.value = false
}

const onDataChanged = () => {
  if (!isInternalOperation.value) {
    checkUnsavedChanges()
  }
}

const beforeUnloadHandler = (e) => {
  if (isInternalOperation.value) return
  if (hasUnsavedChanges.value) {
    e.preventDefault()
    e.returnValue = 'У вас есть несохранённые изменения. Вы уверены, что хотите покинуть страницу?'
    return e.returnValue
  }
}

const currentDishesForCheck = computed(() => {
  if (!originalDiet.value) return []
  const dishes = []
  for (const meal of originalDiet.value.meals) {
    for (const item of meal.items) {
      if (!excludedItems.value.has(item.uniqueId)) {
        dishes.push({
          dish_id: item.id,
          mass: item.displayMass || item.mass
        })
      }
    }
  }
  return dishes
})

const totalDishesCount = computed(() => {
  if (!originalDiet.value) return 0
  let count = 0
  for (const meal of originalDiet.value.meals) {
    for (const item of meal.items) {
      if (!excludedItems.value.has(item.uniqueId)) {
        count++
      }
    }
  }
  return count
})

const hasActiveDishes = computed(() => {
  if (!originalDiet.value) return false
  for (const meal of originalDiet.value.meals) {
    for (const item of meal.items) {
      if (!excludedItems.value.has(item.uniqueId)) {
        return true
      }
    }
  }
  return false
})

const openMicronutrientsModal = () => {
  showMicronutrientsModal.value = true
}

const openIngredientsModal = () => {
  showIngredientsModal.value = true
}

const saveExcludedIngredients = (excludedIds) => {
  excludedIngredients.value = excludedIds
  localStorage.setItem('constructor_excluded_ingredients', JSON.stringify(excludedIds))
  if (hasGeneratedOnce.value && targetKbju.value) {
    regenerateDiet()
  }
}

const goToDish = (id) => {
  router.push(`/dish/${id}`)
}

const setPlannedMealsCount = (count) => {
  plannedMealsCount.value = count
  localStorage.setItem('constructor_planned_meals_count', count.toString())
  onDataChanged()
}

const clearDiet = () => {
  if (!originalDiet.value) return
  
  const baseMeals = originalDiet.value.meals.filter(meal => 
    meal.meal_time_id === 1 || meal.meal_time_id === 2 || meal.meal_time_id === 4
  )
  
  baseMeals.forEach(meal => {
    meal.items = []
  })
  
  originalDiet.value.meals = baseMeals
  excludedItems.value.clear()
  multipliers.value.clear()
  
  editingRationId.value = null
  currentRationName.value = ''
  
  updateDisplayedDiet()
  saveToStorage()
  onDataChanged()
}

const addNewMeal = () => {
  if (!originalDiet.value) return
  
  const currentMeals = [...originalDiet.value.meals]
  const existingIds = currentMeals.map(m => m.meal_time_id)
  
  if (!existingIds.includes(3) && sortedMeals.value.length < 5) {
    const newMeal = {
      meal_time_id: 3,
      items: []
    }
    const lunchIndex = currentMeals.findIndex(m => m.meal_time_id === 2)
    const insertIndex = lunchIndex + 1
    originalDiet.value.meals.splice(insertIndex, 0, newMeal)
    updateDisplayedDiet()
    saveToStorage()
    onDataChanged()
  } 
  else if (!existingIds.includes(5) && sortedMeals.value.length < 5) {
    const newMeal = {
      meal_time_id: 5,
      items: []
    }
    const breakfastIndex = currentMeals.findIndex(m => m.meal_time_id === 1)
    const insertIndex = breakfastIndex + 1
    originalDiet.value.meals.splice(insertIndex, 0, newMeal)
    updateDisplayedDiet()
    saveToStorage()
    onDataChanged()
  }
}

const removeMeal = (mealId) => {
  if (!originalDiet.value) return
  if (mealId === 1 || mealId === 2 || mealId === 4) return
  
  const index = originalDiet.value.meals.findIndex(m => m.meal_time_id === mealId)
  if (index > -1) {
    originalDiet.value.meals.splice(index, 1)
    updateDisplayedDiet()
    saveToStorage()
    onDataChanged()
  }
}

const updateMultiplier = (uniqueId, delta) => {
  const currentMultiplier = multipliers.value.get(uniqueId) || 1
  let newMultiplier = currentMultiplier + delta
  newMultiplier = Math.max(0.7, Math.min(1.25, newMultiplier))
  newMultiplier = Math.round(newMultiplier * 100) / 100
  
  multipliers.value.set(uniqueId, newMultiplier)
  updateDisplayedDiet()
  saveToStorage()
  onDataChanged()
}

const getScaledItem = (item) => {
  if (item.portion_type !== 'scalable') {
    return {
      ...item,
      displayMass: item.mass,
      displayCalories: item.calories,
      displayProteins: item.proteins,
      displayFats: item.fats,
      displayCarbs: item.carbs
    }
  }
  
  const multiplier = multipliers.value.get(item.uniqueId) || item.multiplier || 1
  const baseMass = item.mass / item.multiplier
  const baseCalories = item.calories / item.multiplier
  const baseProteins = item.proteins / item.multiplier
  const baseFats = item.fats / item.multiplier
  const baseCarbs = item.carbs / item.multiplier
  
  return {
    ...item,
    multiplier: multiplier,
    displayMass: baseMass * multiplier,
    displayCalories: baseCalories * multiplier,
    displayProteins: baseProteins * multiplier,
    displayFats: baseFats * multiplier,
    displayCarbs: baseCarbs * multiplier
  }
}

const saveRation = async (rationName) => {
  if (!hasActiveDishes.value) {
    alert('Нет блюд для сохранения. Добавьте хотя бы одно блюдо.')
    return
  }
  
  if (!userStore.isAuthenticated) {
    alert('Необходимо авторизоваться для сохранения рациона')
    router.push('/auth')
    return
  }

  isSaving.value = true
  
  const mealsData = []
  for (const meal of originalDiet.value.meals) {
    const mealData = {
      meal_time_id: meal.meal_time_id,
      items: []
    }
    
    for (const item of meal.items) {
      if (excludedItems.value.has(item.uniqueId)) continue
      
      mealData.items.push({
        dish_id: item.id,
        multiplier: multipliers.value.get(item.uniqueId) || item.multiplier || 1,
        excluded: false
      })
    }
    
    if (mealData.items.length > 0) {
      mealsData.push(mealData)
    }
  }
  
  const requestBody = {
    name: rationName,
    totals: {
      calories: currentTotals.value.k,
      proteins: currentTotals.value.p,
      fats: currentTotals.value.f,
      carbohydrates: currentTotals.value.c
    },
    meals: mealsData
  }
  
  try {
    const token = userStore.token
    let response
    
    if (editingRationId.value) {
      response = await fetch(API_ENDPOINTS.RATION(editingRationId.value), {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
          'X-HTTP-Method-Override': 'PUT'
        },
        body: JSON.stringify(requestBody)
      })
    } else {
      response = await fetch(API_ENDPOINTS.RATIONS, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(requestBody)
      })
    }
    
    const result = await response.json()
    
    if (!response.ok) {
      throw new Error(result.message || 'Ошибка сохранения')
    }
    
    alert(editingRationId.value ? 'Рацион успешно обновлён!' : 'Рацион успешно сохранён!')
    showSaveModal.value = false
    
    if (!editingRationId.value && result.data?.ration_id) {
      editingRationId.value = result.data.ration_id
      router.push(`/constructor?rationId=${result.data.ration_id}`)
    }
    
    currentRationName.value = rationName
    if (originalDiet.value) {
      originalDiet.value.name = rationName
      saveToStorage()
    }
    
    markAsSaved()
    
  } catch (error) {
    console.error('Ошибка:', error)
    alert('Не удалось сохранить рацион: ' + error.message)
  } finally {
    isSaving.value = false
  }
}

const openSaveModal = () => {
  if (!originalDiet.value) {
    alert('Нет рациона для сохранения')
    return
  }
  
  if (!hasActiveDishes.value) {
    alert('Нет блюд для сохранения. Добавьте хотя бы одно блюдо.')
    return
  }
  
  if (editingRationId.value && currentRationName.value) {
    saveRation(currentRationName.value)
    return
  }
  
  const today = new Date()
  const defaultName = `Рацион от ${today.toLocaleDateString('ru-RU')}`
  currentRationName.value = defaultName
  
  showSaveModal.value = true
}

const startEditName = () => {
  editNameValue.value = currentRationName.value
  isEditingName.value = true
}

const updateRationNameOnly = async (newName) => {
  if (!editingRationId.value || !userStore.isAuthenticated) return
  
  try {
    const token = userStore.token
    const response = await fetch(API_ENDPOINTS.RATION(editingRationId.value), {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
        'X-HTTP-Method-Override': 'PUT'
      },
      body: JSON.stringify({ name: newName })
    })
    
    if (!response.ok) {
      console.error('Ошибка обновления названия')
    }
  } catch (error) {
    console.error('Ошибка при обновлении названия:', error)
  }
}

const saveNewName = () => {
  if (!editNameValue.value.trim()) {
    alert('Название не может быть пустым')
    return
  }
  
  const newName = editNameValue.value.trim()
  currentRationName.value = newName
  
  if (originalDiet.value) {
    originalDiet.value.name = newName
  }
  
  saveToStorage()
  
  if (editingRationId.value && userStore.isAuthenticated) {
    updateRationNameOnly(newName)
  }
  
  isEditingName.value = false
  onDataChanged()
}

const cancelEditName = () => {
  isEditingName.value = false
  editNameValue.value = ''
}

const initMultipliers = (diet) => {
  const newMultipliers = new Map()
  for (const meal of diet.meals) {
    for (const item of meal.items) {
      if (item.portion_type === 'scalable') {
        newMultipliers.set(item.uniqueId, item.multiplier || 1)
      }
    }
  }
  multipliers.value = newMultipliers
}

const loadFromStorage = () => {
  const saved = localStorage.getItem('constructor_data')
  if (saved) {
    try {
      const data = JSON.parse(saved)
      if (data.form) form.value = { ...form.value, ...data.form }
      if (data.targetKbju) targetKbju.value = data.targetKbju
      if (data.originalDiet) {
        originalDiet.value = data.originalDiet
        if (data.excludedItems) excludedItems.value = new Set(data.excludedItems)
        if (data.multipliers) {
          const multMap = new Map()
          Object.entries(data.multipliers).forEach(([k, v]) => multMap.set(k, v))
          multipliers.value = multMap
        }
        if (data.editingRationId) editingRationId.value = data.editingRationId
        if (data.currentRationName) currentRationName.value = data.currentRationName
        updateDisplayedDiet()
        hasGeneratedOnce.value = true
      }
    } catch (e) { console.error('Ошибка загрузки:', e) }
  }
  
  const savedPlannedCount = localStorage.getItem('constructor_planned_meals_count')
  if (savedPlannedCount) {
    plannedMealsCount.value = parseInt(savedPlannedCount)
  }
  
  const savedExcluded = localStorage.getItem('constructor_excluded_ingredients')
  if (savedExcluded) {
    try {
      excludedIngredients.value = JSON.parse(savedExcluded)
    } catch (e) {
      console.error('Ошибка загрузки исключённых ингредиентов:', e)
    }
  }
}

const saveToStorage = () => {
  const multipliersObj = {}
  for (const [k, v] of multipliers.value.entries()) multipliersObj[k] = v
  
  const data = {
    form: form.value,
    targetKbju: targetKbju.value,
    originalDiet: originalDiet.value,
    excludedItems: [...excludedItems.value],
    multipliers: multipliersObj,
    editingRationId: editingRationId.value,
    currentRationName: currentRationName.value
  }
  localStorage.setItem('constructor_data', JSON.stringify(data))
}

const form = ref({
  gender: 'female',
  height: 173,
  weight: 61,
  age: 30,
  goal: 'maintain',
  activity_level: 'low'
})

const targetKbju = ref(null)
const originalDiet = ref(null)
const displayedDiet = ref(null)
const excludedItems = ref(new Set())
const multipliers = ref(new Map())
const isCalculating = ref(false)
const isGenerating = ref(false)
const hasGeneratedOnce = ref(false)

const round = (value) => Math.round(value)

const getMealTitle = (id) => {
  const titles = { 
    1: 'Завтрак', 
    2: 'Обед',
    3: 'Полдник',
    4: 'Ужин',
    5: 'Второй завтрак'
  }
  return titles[id] || 'Приём пищи'
}

const getNutrientClass = (nutrient) => {
  let current, target
  
  switch(nutrient) {
    case 'proteins':
      current = currentTotals.value.p
      target = targetKbju.value.proteins
      break
    case 'fats':
      current = currentTotals.value.f
      target = targetKbju.value.fats
      break
    case 'carbs':
      current = currentTotals.value.c
      target = targetKbju.value.carbohydrates
      break
    case 'calories':
      current = currentTotals.value.k
      target = targetKbju.value.calories
      break
    default:
      return ''
  }
  
  const percentage = (current / target) * 100
  
  if (percentage >= 90 && percentage <= 110) return 'nutrient-good'
  if ((percentage >= 75 && percentage < 90) || (percentage > 110 && percentage <= 125)) return 'nutrient-warning'
  return 'nutrient-bad'
}

const sortedMeals = computed(() => {
  if (!displayedDiet.value) return []
  const order = [1, 5, 2, 3, 4]
  return [...displayedDiet.value.meals].sort((a, b) => {
    return order.indexOf(a.meal_time_id) - order.indexOf(b.meal_time_id)
  })
})

const currentTotals = computed(() => {
  if (!originalDiet.value) return { k: 0, p: 0, f: 0, c: 0 }
  
  let totals = { k: 0, p: 0, f: 0, c: 0 }
  
  for (const meal of originalDiet.value.meals) {
    for (const item of meal.items) {
      if (!excludedItems.value.has(item.uniqueId)) {
        const scaledItem = getScaledItem(item)
        totals.k += scaledItem.displayCalories
        totals.p += scaledItem.displayProteins
        totals.f += scaledItem.displayFats
        totals.c += scaledItem.displayCarbs
      }
    }
  }
  
  return {
    k: totals.k,
    p: totals.p,
    f: totals.f,
    c: totals.c
  }
})

const updateDisplayedDiet = () => {
  if (!originalDiet.value) return
  
  const newDiet = {
    meals: originalDiet.value.meals.map(meal => ({
      ...meal,
      items: meal.items
        .filter(item => !excludedItems.value.has(item.uniqueId))
        .map(item => getScaledItem(item))
    })),
    totals: currentTotals.value,
    target: originalDiet.value.target
  }
  
  displayedDiet.value = newDiet
}

const excludeItem = (uniqueId) => {
  if (!uniqueId) return
  excludedItems.value.add(uniqueId)
  updateDisplayedDiet()
  saveToStorage()
  onDataChanged()
}

const addDishToMeal = (dish, mealId) => {
  if (!originalDiet.value || !mealId) return
  
  let meal = originalDiet.value.meals.find(m => m.meal_time_id === mealId)
  
  if (!meal) {
    const newMeal = {
      meal_time_id: mealId,
      items: []
    }
    originalDiet.value.meals.push(newMeal)
    meal = newMeal
  }
  
  const uniqueId = Date.now() + '-' + Math.random().toString(36).substr(2, 9) + '-' + dish.id
  
  const newItem = {
    uniqueId: uniqueId,
    id: dish.id,
    name: dish.name,
    portion_type: dish.portion_type || 'scalable',
    multiplier: 1,
    mass: dish.mass,
    calories: dish.calories,
    proteins: dish.proteins,
    fats: dish.fats,
    carbs: dish.carbs,
    displayMass: dish.mass,
    displayCalories: dish.calories,
    displayProteins: dish.proteins,
    displayFats: dish.fats,
    displayCarbs: dish.carbs
  }
  
  meal.items.push(newItem)
  
  if (dish.portion_type === 'scalable') {
    multipliers.value.set(uniqueId, 1)
  }
  
  updateDisplayedDiet()
  saveToStorage()
  onDataChanged()
}

const openDishSelector = (mealId, mealTitle) => {
  saveToStorage()
  const params = { select: 'true', mealTitle: encodeURIComponent(mealTitle), mealId: mealId }
  if (editingRationId.value) {
    params.rationId = editingRationId.value
  }
  router.push({ path: '/catalog', query: params })
}

const handleSelectedDish = () => {
  const selectedData = localStorage.getItem('selected_dish_for_constructor')
  if (selectedData) {
    try {
      const { dish, mealId } = JSON.parse(selectedData)
      addDishToMeal(dish, mealId)
      localStorage.removeItem('selected_dish_for_constructor')
    } catch (e) {
      console.error('Ошибка парсинга выбранного блюда:', e)
    }
  }
}

const loadRationById = async (rationId) => {
  isLoadingRation.value = true
  editingRationId.value = parseInt(rationId)
  
  // Сначала проверяем localStorage
  const saved = localStorage.getItem('constructor_data')
  let hasLocalData = false
  
  if (saved) {
    try {
      const data = JSON.parse(saved)
      // Если в localStorage есть данные с тем же ID рациона
      if (data.originalDiet && data.editingRationId === parseInt(rationId)) {
        originalDiet.value = data.originalDiet
        if (data.excludedItems) excludedItems.value = new Set(data.excludedItems)
        if (data.multipliers) {
          const multMap = new Map()
          Object.entries(data.multipliers).forEach(([k, v]) => multMap.set(k, v))
          multipliers.value = multMap
        }
        if (data.currentRationName) currentRationName.value = data.currentRationName
        updateDisplayedDiet()
        hasGeneratedOnce.value = true
        hasLocalData = true
        markAsSaved()
        console.log('Загружено из localStorage')
      }
    } catch (e) { console.error('Ошибка загрузки из localStorage:', e) }
  }
  
  // Если в localStorage нет свежих данных, загружаем с сервера
  if (!hasLocalData) {
    try {
      const token = userStore.token
      const response = await fetch(API_ENDPOINTS.RATION(rationId), {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      })
      
      if (!response.ok) throw new Error('Ошибка загрузки рациона')
      
      const result = await response.json()
      if (result.success && result.data) {
        const rationData = result.data
        
        const convertedDiet = {
          name: rationData.name,
          meals: rationData.meals.map(meal => ({
            meal_time_id: getMealTimeIdByName(meal.meal_type),
            items: meal.items.map(item => {
              const uniqueId = Date.now() + '-' + Math.random().toString(36).substr(2, 9) + '-' + item.id
              return {
                uniqueId: uniqueId,
                id: item.id,
                name: item.name,
                portion_type: item.portion_type || 'scalable',
                multiplier: item.multiplier || 1,
                mass: item.mass,
                calories: item.calories || item.base_calories,
                proteins: item.proteins || item.base_proteins,
                fats: item.fats || item.base_fats,
                carbs: item.carbs || item.base_carbs,
                displayMass: item.mass,
                displayCalories: item.calories || item.base_calories,
                displayProteins: item.proteins || item.base_proteins,
                displayFats: item.fats || item.base_fats,
                displayCarbs: item.carbs || item.base_carbs
              }
            })
          }))
        }
        
        const requiredMeals = [1, 2, 4]
        for (const requiredId of requiredMeals) {
          if (!convertedDiet.meals.find(m => m.meal_time_id === requiredId)) {
            convertedDiet.meals.push({
              meal_time_id: requiredId,
              items: []
            })
          }
        }
        
        originalDiet.value = convertedDiet
        currentRationName.value = rationData.name
        excludedItems.value.clear()
        initMultipliers(convertedDiet)
        updateDisplayedDiet()
        hasGeneratedOnce.value = true
        saveToStorage()
        
        markAsSaved()
        console.log('Загружено с сервера')
      }
    } catch (error) {
      console.error('Ошибка загрузки рациона:', error)
      alert('Не удалось загрузить рацион')
    }
  }
  
  isLoadingRation.value = false
}

const getMealTimeIdByName = (mealType) => {
  const map = {
    'Завтрак': 1,
    'Второй завтрак': 5,
    'Обед': 2,
    'Полдник': 3,
    'Ужин': 4
  }
  return map[mealType] || 1
}

const clearRationContext = () => {
  editingRationId.value = null
  currentRationName.value = ''
  isEditingName.value = false
  editNameValue.value = ''
  
  const query = { ...route.query }
  delete query.rationId
  router.replace({ query })
  
  saveToStorage()
  markAsSaved()
}

const generateDiet = async () => {
  if (!targetKbju.value) return
  
  isInternalOperation.value = true
  isGenerating.value = true
  try {
    const requestBody = {
      calories: targetKbju.value.calories,
      proteins: targetKbju.value.proteins,
      fats: targetKbju.value.fats,
      carbs: targetKbju.value.carbohydrates,
      meals: plannedMealsCount.value,
      exclude_ingredients: excludedIngredients.value
    }
    
    const response = await fetch(API_ENDPOINTS.GENERATE_DIET, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(requestBody)
    })
    
    if (!response.ok) throw new Error('Ошибка генерации')
    
    let result = await response.json()
    
    const requiredMeals = [1, 2, 4]
    for (const requiredId of requiredMeals) {
      if (!result.meals.find(m => m.meal_time_id === requiredId)) {
        result.meals.push({
          meal_time_id: requiredId,
          items: []
        })
      }
    }
    
    result = {
      ...result,
      meals: result.meals.map(meal => ({
        ...meal,
        items: meal.items.map(item => ({
          ...item,
          uniqueId: Date.now() + '-' + Math.random().toString(36).substr(2, 9) + '-' + item.id + '-' + Math.random()
        }))
      }))
    }
    
    originalDiet.value = result
    excludedItems.value.clear()
    initMultipliers(result)
    updateDisplayedDiet()
    hasGeneratedOnce.value = true
    saveToStorage()
    
    markAsSaved()
    
  } catch (error) {
    console.error('Ошибка:', error)
    alert('Не удалось сгенерировать рацион.')
  } finally {
    isGenerating.value = false
    isInternalOperation.value = false
  }
}

const regenerateDiet = async () => {
  if (!targetKbju.value) {
    alert('Сначала рассчитайте норму')
    return
  }
  if (editingRationId.value) {
    const confirmReset = confirm('Вы работаете с сохранённым рационом. Генерация нового рациона заменит текущий и вы потеряете несохранённые изменения. Продолжить?')
    if (!confirmReset) {
      return
    }
    clearRationContext()
  }
  await generateDiet()
}

const calculateNorm = async () => {
  isCalculating.value = true
  try {
    const response = await fetch(API_ENDPOINTS.CALCULATE, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(form.value)
    })
    
    if (!response.ok) throw new Error('Ошибка расчёта')
    
    const result = await response.json()
    targetKbju.value = result.data
    saveToStorage()
    
    if (!hasGeneratedOnce.value) {
      await generateDiet()
    }
  } catch (error) {
    console.error('Ошибка:', error)
    alert('Не удалось рассчитать норму. Проверьте подключение к серверу.')
  } finally {
    isCalculating.value = false
  }
}

onMounted(async () => {
  loadFromStorage()
  
  if (!originalDiet.value) {
    originalDiet.value = {
      meals: [
        { meal_time_id: 1, items: [] },
        { meal_time_id: 2, items: [] },
        { meal_time_id: 4, items: [] }
      ]
    }
  }
  
  const rationId = route.query.rationId
  if (rationId) {
    // Проверяем, нужно ли загружать с сервера или уже есть в localStorage
    const currentSavedId = editingRationId.value
    if (currentSavedId !== parseInt(rationId)) {
      await loadRationById(rationId)
    } else {
      // Уже загружено из localStorage, просто обновляем отображение
      updateDisplayedDiet()
      markAsSaved()
    }
  } else {
    editingRationId.value = null
    currentRationName.value = ''
    
    if (originalDiet.value && originalDiet.value.meals) {
      const requiredMeals = [1, 2, 4]
      for (const requiredId of requiredMeals) {
        if (!originalDiet.value.meals.find(m => m.meal_time_id === requiredId)) {
          originalDiet.value.meals.push({
            meal_time_id: requiredId,
            items: []
          })
        }
      }
      updateDisplayedDiet()
    }
    markAsSaved()
  }
  
  handleSelectedDish()
  
  watch(() => route.query.rationId, (newRationId, oldRationId) => {
    if (!newRationId && oldRationId) {
      editingRationId.value = null
      currentRationName.value = ''
    }
  })
  
  window.addEventListener('beforeunload', beforeUnloadHandler)
})

onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', beforeUnloadHandler)
})

watch([form], () => saveToStorage(), { deep: true })
</script>

<style scoped>
.constructor-page {
  min-height: calc(100vh - 70px);
  padding: 30px 5%;
  max-width: 1200px;
  margin: 0 auto;
  background: #f5f5f5;
}

.dark .constructor-page {
  background: hsl(207, 26%, 17%);
}

.form-wrapper {
  max-width: 1000px;
  margin: 0 auto 40px auto;
}

.form-section {
  background: white;
  padding: 25px;
  border-radius: 12px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.05);
  transition: all 0.3s;
}

.dark .form-section,
.dark .norms-block,
.dark .diet-block {
  background: hsl(207, 26%, 22%) !important;
  color: #ffffff !important;
  box-shadow: 0 4px 20px rgba(0,0,0,0.3);
  border: 1px solid rgba(255,255,255,0.1);
}

.section-title {
  font-size: 18px;
  margin-bottom: 25px;
  font-weight: 500;
  text-align: center;
  color: inherit;
}

.params-table {
  margin-bottom: 30px;
}

.table-row {
  display: grid;
  grid-template-columns: 80px 1fr 60px 1fr 60px 1fr;
  gap: 15px;
  align-items: center;
  margin-bottom: 20px;
}

/* Сдвигаем поле выбора "Уровень физ. активности" вправо */
.activity-row .table-value:nth-child(4) {
  padding-left: 20px;
}

.table-label {
  font-weight: 500;
  font-size: 14px;
  color: #555;
}

.dark .table-label {
  color: #e0e0e0;
}

.table-value {
  display: flex;
  align-items: center;
  gap: 8px;
}

.gender-buttons {
  display: flex;
  gap: 10px;
}

.gender-btn {
  padding: 6px 16px;
  border: 1px solid #ddd;
  background: white;
  border-radius: 6px;
  cursor: pointer;
  font-size: 14px;
  transition: all 0.2s;
}

.dark .gender-btn {
  background: hsl(207, 26%, 28%);
  color: white;
  border-color: rgba(255,255,255,0.2);
}

.dark .gender-btn:hover {
  background: hsl(207, 26%, 35%);
}

.gender-btn.active {
  background: #4C9E2C;
  color: white;
  border-color: #4C9E2C;
}

.param-input {
  width: 70px;
  padding: 6px 8px;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 14px;
  transition: all 0.2s;
}

.dark .param-input,
.dark .param-select {
  background: hsl(207, 26%, 28%);
  color: white;
  border-color: rgba(255,255,255,0.2);
}

.dark .param-input:focus,
.dark .param-select:focus {
  border-color: #4C9E2C;
  outline: none;
}

.param-select {
  padding: 6px 8px;
  border: 1px solid #ddd;
  border-radius: 6px;
  background: white;
  min-width: 130px;
  font-size: 14px;
}

.param-unit {
  font-size: 12px;
  color: #888;
}

.dark .param-unit {
  color: #aaa;
}

.calculate-btn {
  width: 100%;
  padding: 12px;
  background: #4C9E2C;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.calculate-btn:hover:not(:disabled) {
  background: #3d7e23;
  transform: translateY(-1px);
}

.calculate-btn:disabled {
  background: #ccc;
  cursor: not-allowed;
}

.results-wrapper {
  max-width: 1200px;
  margin: 0 auto;
}

.norms-block, .diet-block {
  background: white;
  padding: 20px;
  border-radius: 12px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.05);
  margin-bottom: 25px;
}

.norms-grid, .actual-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
  margin-top: 15px;
}

.norm-item {
  padding: 8px;
  background: #f9f9f9;
  border-radius: 6px;
  font-size: 14px;
  color: #555;
  text-align: center;
}

.actual-item {
  padding: 8px;
  border-radius: 6px;
  font-size: 14px;
  text-align: center;
  transition: all 0.3s ease;
  font-weight: 500;
  background: #f9f9f9;
}

.dark .norm-item {
  background: hsl(207, 26%, 28%);
  color: #e0e0e0;
}

.dark .actual-item {
  background: hsl(207, 26%, 28%);
  color: #e0e0e0;
}

.nutrient-good {
  background: rgba(76, 158, 44, 0.15) !important;
  color: inherit !important;
  border-left: 3px solid #4C9E2C !important;
}

.nutrient-warning {
  background: rgba(255, 152, 0, 0.12) !important;
  color: inherit !important;
  border-left: 3px solid #ff9800 !important;
}

.nutrient-bad {
  background: rgba(244, 67, 54, 0.1) !important;
  color: inherit !important;
  border-left: 3px solid #f44336 !important;
}

.dark .nutrient-good {
  background: rgba(76, 158, 44, 0.25) !important;
  color: #ffffff !important;
  border-left: 3px solid #6abf4a !important;
}

.dark .nutrient-warning {
  background: rgba(255, 152, 0, 0.22) !important;
  color: #ffffff !important;
  border-left: 3px solid #ffb74d !important;
}

.dark .nutrient-bad {
  background: rgba(244, 67, 54, 0.2) !important;
  color: #ffffff !important;
  border-left: 3px solid #ef9a9a !important;
}

.diet-header {
  margin-bottom: 25px;
}

.ration-title-section {
  background: rgba(76, 158, 44, 0.1);
  border-radius: 12px;
  padding: 12px 16px;
  margin: 15px 0;
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.dark .ration-title-section {
  background: rgba(106, 191, 74, 0.15);
}

.ration-title-label {
  font-size: 14px;
  font-weight: 600;
  color: #4C9E2C;
}

.dark .ration-title-label {
  color: #6abf4a;
}

.ration-title-display {
  display: flex;
  align-items: center;
  gap: 10px;
  flex: 1;
}

.ration-title-name {
  font-size: 16px;
  font-weight: 700;
  color: #333;
  padding: 6px 12px;
  background: white;
  border-radius: 8px;
  flex: 1;
}

.dark .ration-title-name {
  background: hsl(207, 26%, 28%);
  color: white;
}

.edit-name-btn {
  background: none;
  border: none;
  cursor: pointer;
  font-size: 16px;
  padding: 6px;
  border-radius: 6px;
  transition: all 0.2s;
  color: #4C9E2C;
}

.edit-name-btn:hover {
  background: rgba(76, 158, 44, 0.1);
  transform: scale(1.05);
}

.ration-title-edit {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 1;
}

.ration-title-input {
  flex: 1;
  padding: 8px 12px;
  border: 1px solid #4C9E2C;
  border-radius: 8px;
  font-size: 16px;
  font-weight: 700;
  background: white;
}

.dark .ration-title-input {
  background: hsl(207, 26%, 28%);
  color: white;
  border-color: #6abf4a;
}

.ration-title-input:focus {
  outline: none;
}

.save-name-btn, .cancel-name-btn {
  background: none;
  border: none;
  cursor: pointer;
  font-size: 16px;
  padding: 6px;
  border-radius: 6px;
  transition: all 0.2s;
}

.save-name-btn {
  color: #4C9E2C;
}

.save-name-btn:hover {
  background: rgba(76, 158, 44, 0.1);
  transform: scale(1.05);
}

.cancel-name-btn {
  color: #f44336;
}

.cancel-name-btn:hover {
  background: rgba(244, 67, 54, 0.1);
  transform: scale(1.05);
}

.diet-controls {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin: 15px 0;
  flex-wrap: wrap;
  gap: 15px;
}

.left-controls {
  display: flex;
  gap: 20px;
  flex-wrap: wrap;
  align-items: center;
}

.right-controls {
  display: flex;
  gap: 20px;
  align-items: center;
  flex-wrap: wrap;
}

.link {
  color: #4C9E2C;
  text-decoration: none;
  font-size: 14px;
  cursor: pointer;
  padding: 6px 14px;
  border-radius: 20px;
  transition: all 0.2s;
  font-weight: 500;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.link:hover {
  background: rgba(76, 158, 44, 0.1);
  text-decoration: none;
  transform: translateY(-1px);
}

.dark .link {
  color: #6abf4a;
}

.dark .link:hover {
  background: rgba(106, 191, 74, 0.15);
}

.clear-link {
  color: #ff4444 !important;
  background: rgba(255, 68, 68, 0.1);
  border: 1px solid rgba(255, 68, 68, 0.3);
}

.clear-link:hover {
  background: rgba(255, 68, 68, 0.2) !important;
  color: #ff6666 !important;
  transform: translateY(-1px);
}

.dark .clear-link {
  color: #ff8888 !important;
  background: rgba(255, 68, 68, 0.15);
  border: 1px solid rgba(255, 68, 68, 0.3);
}

.dark .clear-link:hover {
  background: rgba(255, 68, 68, 0.25) !important;
  color: #ffaaaa !important;
}

/* Неактивная ссылка */
.disabled-link {
  opacity: 0.5;
  cursor: not-allowed !important;
  pointer-events: none;
  color: #999 !important;
}

.dark .disabled-link {
  color: #666 !important;
}

.disabled-link:hover {
  background: none !important;
  transform: none !important;
}

.meals-count-control {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
}

.count-buttons {
  display: flex;
  gap: 5px;
}

.count-btn {
  width: 32px;
  height: 32px;
  border: 1px solid #ddd;
  background: white;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
}

.dark .count-btn {
  background: hsl(207, 26%, 28%);
  color: white;
  border-color: rgba(255,255,255,0.2);
}

.count-btn.active {
  background: #4C9E2C;
  color: white;
  border-color: #4C9E2C;
}

.save-btn {
  padding: 8px 20px;
  background: #2196F3;
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 14px;
  transition: all 0.2s;
}

.save-btn:hover:not(:disabled) {
  background: #1976D2;
  transform: translateY(-1px);
}

.save-btn:disabled {
  background: #ccc;
  cursor: not-allowed;
}

.dark .save-btn {
  background: #1976D2;
}

.dark .save-btn:hover:not(:disabled) {
  background: #0b5da7;
}

.update-btn {
  padding: 8px 20px;
  background: #4C9E2C;
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 14px;
  transition: all 0.2s;
}

.update-btn:hover:not(:disabled) {
  background: #3d7e23;
  transform: translateY(-1px);
}

.update-btn:disabled {
  background: #ccc;
}

.meals-list {
  display: flex;
  flex-direction: column;
  gap: 30px;
  margin-top: 20px;
}

.meal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
  flex-wrap: wrap;
  gap: 10px;
}

.meal-title-wrapper {
  display: flex;
  align-items: center;
  gap: 12px;
}

.meal-title {
  font-size: 20px;
  font-weight: 600;
  color: inherit;
  border-left: 4px solid #4C9E2C;
  padding-left: 15px;
  margin: 0;
}

.remove-meal-btn {
  background: none;
  border: none;
  cursor: pointer;
  font-size: 18px;
  padding: 4px 8px;
  border-radius: 6px;
  transition: all 0.2s;
  color: #ff4444;
}

.remove-meal-btn:hover {
  background: rgba(255, 68, 68, 0.1);
  transform: scale(1.05);
}

.dark .remove-meal-btn {
  color: #ff6666;
}

.dark .remove-meal-btn:hover {
  background: rgba(255, 68, 68, 0.2);
}

.add-dish-btn {
  padding: 6px 16px;
  background: #4C9E2C;
  color: white;
  border: none;
  border-radius: 20px;
  font-size: 13px;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 4px;
  transition: all 0.2s;
}

.add-dish-btn:hover {
  background: #3d7e23;
  transform: scale(1.02);
}

.items-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 20px;
}

.card-with-controls {
  position: relative;
  display: flex;
  flex-direction: column;
}

.scale-controls {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  margin-top: 8px;
  margin-bottom: 8px;
  padding: 6px 12px;
  background: rgba(76, 158, 44, 0.1);
  border-radius: 32px;
  width: fit-content;
  align-self: center;
}

.dark .scale-controls {
  background: rgba(76, 158, 44, 0.25);
}

.scale-btn {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  border: none;
  background: #4C9E2C;
  color: white;
  cursor: pointer;
  font-size: 18px;
  font-weight: bold;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.scale-btn:hover:not(:disabled) {
  background: #3d7e23;
  transform: scale(1.05);
}

.scale-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
  transform: none;
}

.multiplier-badge {
  font-size: 13px;
  font-weight: 700;
  min-width: 55px;
  text-align: center;
  padding: 4px 10px;
  background: white;
  border-radius: 20px;
  color: #4C9E2C;
  box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.dark .multiplier-badge {
  background: hsl(207, 26%, 28%);
  color: #6abf4a;
}

.exclude-btn {
  position: absolute;
  top: 8px;
  right: 8px;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.9);
  border: 1px solid #ddd;
  cursor: pointer;
  font-size: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
  z-index: 10;
}

.exclude-btn:hover {
  background: #ff4444;
  color: white;
  border-color: #ff4444;
}

.dark .exclude-btn {
  background: rgba(0, 0, 0, 0.7);
  color: white;
  border-color: rgba(255,255,255,0.2);
}

.dark .exclude-btn:hover {
  background: #ff4444;
  color: white;
  border-color: #ff4444;
}

.add-meal-btn {
  width: 100%;
  padding: 12px;
  background: transparent;
  border: 2px dashed #ccc;
  border-radius: 8px;
  font-size: 14px;
  cursor: pointer;
  margin-top: 20px;
  color: inherit;
  transition: all 0.2s;
}

.add-meal-btn:hover {
  border-color: #4C9E2C;
  color: #4C9E2C;
}

.dark .add-meal-btn {
  border-color: rgba(255,255,255,0.3);
  color: #e0e0e0;
}

.dark .add-meal-btn:hover {
  border-color: #6abf4a;
  color: #6abf4a;
}

.empty-meal-message {
  text-align: center;
  padding: 40px;
  background: #f9f9f9;
  border-radius: 12px;
  color: #666;
  font-size: 14px;
}

.dark .empty-meal-message {
  background: hsl(207, 26%, 28%);
  color: #ccc;
}

.loading-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.7);
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  z-index: 9999;
}

.loading-spinner {
  width: 50px;
  height: 50px;
  border: 5px solid #f3f3f3;
  border-top: 5px solid #4C9E2C;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.loading-overlay p {
  color: white;
  margin-top: 20px;
  font-size: 18px;
}

/* ============================================ */
/* АДАПТИВНЫЕ МЕДИА-ЗАПРОСЫ (ИСПРАВЛЕНЫ) */
/* ============================================ */

/* Планшеты и узкие экраны (до 900px) */
@media (max-width: 900px) {
  .norms-grid, .actual-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .diet-controls {
    flex-direction: column;
    align-items: stretch;
  }
  
  .left-controls, .right-controls {
    justify-content: center;
  }
  
  .meals-count-control {
    justify-content: center;
  }
  
  .update-btn, .save-btn {
    width: 100%;
  }
}

/* Мобильные устройства (до 768px) - здесь ломаем таблицу параметров */
@media (max-width: 768px) {
  .constructor-page {
    padding: 20px 15px;
  }
  
  /* ГЛАВНОЕ ИСПРАВЛЕНИЕ: таблица параметров становится вертикальной */
  .table-row {
    grid-template-columns: 1fr !important;
    gap: 8px;
  }
  
  .table-row .table-label {
    margin-bottom: 4px;
    font-weight: 600;
  }
  
  .gender-buttons {
    justify-content: center;
    width: 100%;
  }
  
  .gender-btn {
    flex: 1;
    text-align: center;
  }
  
  .param-input, .param-select {
    width: 100% !important;
    min-width: auto !important;
  }
  
  .table-value {
    flex-wrap: wrap;
  }
  
  .norms-grid, .actual-grid {
    grid-template-columns: 1fr;
  }
  
  .items-grid {
    grid-template-columns: 1fr;
  }
  
  .meal-header {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .meal-title {
    font-size: 18px;
  }
  
  .remove-meal-btn {
    font-size: 16px;
  }
  
  .add-dish-btn {
    padding: 4px 12px;
    font-size: 12px;
  }
  
  .empty-meal-message {
    padding: 20px;
    font-size: 12px;
  }
  
  .link {
    padding: 4px 10px;
    font-size: 12px;
  }
}

/* Очень маленькие телефоны (до 480px) */
@media (max-width: 480px) {
  .constructor-page {
    padding: 15px 10px;
  }
  
  .form-section {
    padding: 15px;
  }
  
  .section-title {
    font-size: 16px;
  }
  
  .gender-btn {
    padding: 6px 12px;
    font-size: 13px;
  }
  
  .param-input, .param-select {
    font-size: 13px;
  }
  
  .meal-title {
    font-size: 16px;
  }
  
  .scale-controls {
    gap: 8px;
    padding: 4px 8px;
  }
  
  .scale-btn {
    width: 28px;
    height: 28px;
    font-size: 16px;
  }
  
  .multiplier-badge {
    font-size: 12px;
    min-width: 45px;
  }
  
  .norms-block, .diet-block {
    padding: 12px;
  }
  
  .norm-item, .actual-item {
    font-size: 12px;
  }
  
  .link {
    font-size: 11px;
    padding: 4px 8px;
  }
  
  .save-btn, .update-btn {
    padding: 6px 12px;
    font-size: 12px;
  }
}

input:focus, select:focus, button:focus {
  outline: none;
}
</style>