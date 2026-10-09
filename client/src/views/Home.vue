<script setup>
import Card from '../components/Card.vue'
import { ref, onMounted, computed, inject, onActivated, onDeactivated, onUnmounted, watch, nextTick } from 'vue'
import axios from 'axios'
import { useRouter, useRoute } from 'vue-router'
import { API_ENDPOINTS } from '@/config/api'

defineOptions({
  name: 'Catalog'
})

const router = useRouter()
const route = useRoute()
const isDark = inject('isDark')
const scrollY = ref(0)

// Режим выбора (из конструктора)
const isSelectionMode = ref(false)
const selectionMealTitle = ref('')
const selectionMealId = ref(null)
const selectionRationId = ref(null)

// Состояние данных
const allItems = ref([])
const isLoading = ref(true)
const currentPage = ref(1)
const lastPage = ref(1)
const total = ref(0)

// Фильтры
const searchQuery = ref('')
const selectedMealTimes = ref([])
const selectedDishTypes = ref([])
const selectedIngredients = ref([])
const selectedMicronutrient = ref(null)
const caloriesFrom = ref('')
const caloriesTo = ref('')
const highProtein = ref(false)

// Данные для фильтров из API
const dishTypesOptions = ref([])
const ingredientsTree = ref([])
const micronutrientsOptions = ref([])
const mealTimesOptions = ref([
  { id: 1, name: 'Завтрак' },
  { id: 2, name: 'Обед' },
  { id: 3, name: 'Перекус' },
  { id: 4, name: 'Ужин' }
])

const expandedGroups = ref({})

// Флаг для предотвращения двойной загрузки при возврате
let isFirstActivation = true

// Состояние панели фильтров - по умолчанию открыта на десктопе
const isFiltersOpen = ref(true)
const isMobileFilterView = ref(false)
const isFiltersLoaded = ref(false)
const isFiltersLoading = ref(false)
const isInitialLoad = ref(true)

// Проверяем ширину экрана
function checkMobileView() {
  const width = window.innerWidth
  const wasMobile = isMobileFilterView.value
  isMobileFilterView.value = width <= 1200
  
  if (!wasMobile && isMobileFilterView.value) {
    isFiltersOpen.value = false
  }
  if (wasMobile && !isMobileFilterView.value) {
    isFiltersOpen.value = true
  }
}

// Переключение фильтров (только для мобильных)
function toggleFilters() {
  isFiltersOpen.value = !isFiltersOpen.value
  if (isFiltersOpen.value && !isFiltersLoaded.value && !isFiltersLoading.value) {
    fetchFilters()
  }
}

// Следим за ресайзом
function handleResize() {
  checkMobileView()
}

// Отслеживаем открытие панели фильтров
watch(isFiltersOpen, (newVal) => {
  if (newVal && !isFiltersLoaded.value && !isFiltersLoading.value) {
    fetchFilters()
  }
})

onMounted(() => {
  checkMobileView()
  window.addEventListener('resize', handleResize)
  
  if (route.query.select === 'true') {
    isSelectionMode.value = true
    const encodedTitle = route.query.mealTitle || 'приёма пищи'
    selectionMealTitle.value = decodeURIComponent(encodedTitle)
    selectionMealId.value = route.query.mealId ? parseInt(route.query.mealId) : null
    selectionRationId.value = route.query.rationId ? parseInt(route.query.rationId) : null
  }
})

onUnmounted(() => {
  window.removeEventListener('resize', handleResize)
})

function selectAndReturn(dish) {
  const selectedDish = {
    id: dish.id,
    name: dish.name,
    portion_type: dish.portion_type || 'scalable',
    multiplier: 1,
    mass: dish.mass,
    calories: dish.calories,
    proteins: dish.proteins,
    fats: dish.fats,
    carbs: dish.carbs
  }
  
  const selectedData = {
    dish: selectedDish,
    mealId: selectionMealId.value,
    mealTitle: selectionMealTitle.value,
    rationId: selectionRationId.value
  }
  localStorage.setItem('selected_dish_for_constructor', JSON.stringify(selectedData))
  
  if (selectionRationId.value) {
    router.push(`/constructor?rationId=${selectionRationId.value}`)
  } else {
    router.push('/constructor')
  }
}

function cancelSelection() {
  if (selectionRationId.value) {
    router.push(`/constructor?rationId=${selectionRationId.value}`)
  } else {
    router.push('/constructor')
  }
}

// Функция для полной очистки состояния фильтров
function forceResetFiltersState() {
  console.log('Принудительный сброс состояния фильтров')
  
  // Очищаем sessionStorage
  sessionStorage.removeItem('catalog_filters')
  
  // Сбрасываем все reactive данные
  searchQuery.value = ''
  selectedMealTimes.value = []
  selectedDishTypes.value = []
  selectedIngredients.value = []
  selectedMicronutrient.value = null
  caloriesFrom.value = ''
  caloriesTo.value = ''
  highProtein.value = false
  currentPage.value = 1
  expandedGroups.value = {}
  isFiltersLoaded.value = false
  isFiltersLoading.value = false
  
  // Принудительно открываем фильтры на десктопе
  if (!isMobileFilterView.value) {
    isFiltersOpen.value = true
  }
}

async function fetchFilters(forceReload = false) {
  if (isFiltersLoading.value && !forceReload) return
  
  isFiltersLoading.value = true
  
  try {
    const response = await axios.get(API_ENDPOINTS.FILTERS)
    const data = response.data
    
    // Проверяем, что данные получены
    if (!data.dish_types && !data.ingredients && !data.micronutrients) {
      throw new Error('Нет данных фильтров')
    }
    
    dishTypesOptions.value = data.dish_types || []
    ingredientsTree.value = data.ingredients || []
    micronutrientsOptions.value = data.micronutrients || []
    
    // Если данные все еще пустые - очищаем состояние и пробуем еще раз
    if (!dishTypesOptions.value.length && !ingredientsTree.value.length && !micronutrientsOptions.value.length) {
      forceResetFiltersState()
      if (!forceReload) {
        return fetchFilters(true)
      }
      return
    }
    
    ingredientsTree.value.forEach(node => {
      expandedGroups.value[node.id] = false
    })
    
    isFiltersLoaded.value = true
    
    // Если фильтры были пустые, теперь они загрузились - сохраняем состояние
    saveFiltersToSession()
    
  } catch (error) {
    console.error('Ошибка при загрузке фильтров:', error)
    forceResetFiltersState()
    // Пробуем еще раз
    if (!forceReload) {
      setTimeout(() => fetchFilters(true), 1000)
    }
  } finally {
    isFiltersLoading.value = false
  }
}

const queryParams = computed(() => {
  const params = {}
  if (searchQuery.value) params.search = searchQuery.value
  if (selectedMealTimes.value.length) params.meal_times = selectedMealTimes.value.join(',')
  if (selectedDishTypes.value.length) params.dish_types = selectedDishTypes.value.join(',')
  if (selectedIngredients.value.length) params.ingredients = selectedIngredients.value.join(',')
  if (selectedMicronutrient.value) params.micronutrient_id = selectedMicronutrient.value
  if (caloriesFrom.value) params.calories_from = caloriesFrom.value
  if (caloriesTo.value) params.calories_to = caloriesTo.value
  if (highProtein.value) params.high_protein = true
  params.page = currentPage.value
  return params
})

const items = computed(() => allItems.value)
const hasNoResults = computed(() => !isLoading.value && total.value === 0)

async function fetchItems() {
  try {
    isLoading.value = true
    const response = await axios.get(API_ENDPOINTS.DISHES, {
      params: queryParams.value
    })
    
    const dishesArray = response.data.data
    currentPage.value = response.data.current_page
    lastPage.value = response.data.last_page
    total.value = response.data.total
    
    if (!Array.isArray(dishesArray)) {
      console.error('Данные не являются массивом:', dishesArray)
      allItems.value = []
      return
    }
    
    allItems.value = dishesArray.map(c => ({
      id: c.id,
      name: c.name,
      proteins: Math.round(parseFloat(c.proteins) || 0),
      fats: Math.round(parseFloat(c.fats) || 0),
      carbs: Math.round(parseFloat(c.carbs) || 0),
      calories: Math.round(parseFloat(c.calories) || 0),
      mass: Math.round(parseFloat(c.mass) || 0),
      image: c.image,
      portion_type: c.portion_type || 'scalable'
    }))
  } catch (error) {
    console.error('Ошибка при загрузке данных:', error)
    allItems.value = []
    total.value = 0
  } finally {
    isLoading.value = false
  }
}

let searchTimeout
const onSearchInput = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    applyFilters()
  }, 500)
}

function applyFilters() {
  currentPage.value = 1
  fetchItems()
}

function resetFilters() {
  searchQuery.value = ''
  selectedMealTimes.value = []
  selectedDishTypes.value = []
  selectedIngredients.value = []
  selectedMicronutrient.value = null
  caloriesFrom.value = ''
  caloriesTo.value = ''
  highProtein.value = false
  currentPage.value = 1
  fetchItems()
}

function goToPage(page) {
  if (page >= 1 && page <= lastPage.value && page !== currentPage.value) {
    currentPage.value = page
    fetchItems()
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }
}

function goToDish(id) {
  saveFiltersToSession()
  
  if (isSelectionMode.value) {
    router.push(`/dish/${id}?from=catalog&select=true&mealId=${selectionMealId.value}&mealTitle=${encodeURIComponent(selectionMealTitle.value)}&rationId=${selectionRationId.value || ''}`)
  } else {
    router.push(`/dish/${id}`)
  }
}

function toggleMealTime(id) {
  const index = selectedMealTimes.value.indexOf(id)
  if (index > -1) selectedMealTimes.value.splice(index, 1)
  else selectedMealTimes.value.push(id)
  applyFilters()
}

function toggleDishType(id) {
  const index = selectedDishTypes.value.indexOf(id)
  if (index > -1) selectedDishTypes.value.splice(index, 1)
  else selectedDishTypes.value.push(id)
  applyFilters()
}

const toggleParentIngredient = (parentId, children) => {
  const isCurrentlySelected = selectedIngredients.value.includes(parentId)
  
  if (!isCurrentlySelected) {
    if (!selectedIngredients.value.includes(parentId)) {
      selectedIngredients.value.push(parentId)
    }
    if (children && children.length) {
      children.forEach(child => {
        if (!selectedIngredients.value.includes(child.id)) {
          selectedIngredients.value.push(child.id)
        }
      })
    }
  } else {
    const parentIndex = selectedIngredients.value.indexOf(parentId)
    if (parentIndex > -1) {
      selectedIngredients.value.splice(parentIndex, 1)
    }
  }
  applyFilters()
}

const toggleChildIngredient = (childId, parentId, siblings) => {
  const isCurrentlySelected = selectedIngredients.value.includes(childId)
  
  if (!isCurrentlySelected) {
    selectedIngredients.value.push(childId)
  } else {
    const childIndex = selectedIngredients.value.indexOf(childId)
    if (childIndex > -1) {
      selectedIngredients.value.splice(childIndex, 1)
    }
  }
  
  const allChildrenSelected = siblings.every(child => selectedIngredients.value.includes(child.id))
  
  if (allChildrenSelected) {
    if (!selectedIngredients.value.includes(parentId)) {
      selectedIngredients.value.push(parentId)
    }
  } else {
    const parentIndex = selectedIngredients.value.indexOf(parentId)
    if (parentIndex > -1) {
      selectedIngredients.value.splice(parentIndex, 1)
    }
  }
  
  applyFilters()
}

function setMicronutrient(id) {
  if (selectedMicronutrient.value === id) selectedMicronutrient.value = null
  else selectedMicronutrient.value = id
  applyFilters()
}

function toggleGroup(groupId) {
  expandedGroups.value[groupId] = !expandedGroups.value[groupId]
}

function saveFiltersToSession() {
  const state = {
    searchQuery: searchQuery.value,
    selectedMealTimes: selectedMealTimes.value,
    selectedDishTypes: selectedDishTypes.value,
    selectedIngredients: selectedIngredients.value,
    selectedMicronutrient: selectedMicronutrient.value,
    caloriesFrom: caloriesFrom.value,
    caloriesTo: caloriesTo.value,
    highProtein: highProtein.value,
    currentPage: currentPage.value,
    expandedGroups: expandedGroups.value,
    isFiltersOpen: isFiltersOpen.value,
    dishTypesOptions: dishTypesOptions.value,
    ingredientsTree: ingredientsTree.value,
    micronutrientsOptions: micronutrientsOptions.value
  }
  sessionStorage.setItem('catalog_filters', JSON.stringify(state))
}

function restoreFiltersFromSession() {
  const saved = sessionStorage.getItem('catalog_filters')
  if (saved) {
    try {
      const state = JSON.parse(saved)
      
      // Проверяем, что в сохраненном состоянии есть данные фильтров
      const hasFilterData = state.dishTypesOptions?.length > 0 || 
                           state.ingredientsTree?.length > 0 || 
                           state.micronutrientsOptions?.length > 0
      
      if (!hasFilterData) {
        console.warn('Сохраненное состояние не содержит данных фильтров, очищаем')
        forceResetFiltersState()
        return false
      }
      
      searchQuery.value = state.searchQuery || ''
      selectedMealTimes.value = state.selectedMealTimes || []
      selectedDishTypes.value = state.selectedDishTypes || []
      selectedIngredients.value = state.selectedIngredients || []
      selectedMicronutrient.value = state.selectedMicronutrient || null
      caloriesFrom.value = state.caloriesFrom || ''
      caloriesTo.value = state.caloriesTo || ''
      highProtein.value = state.highProtein || false
      currentPage.value = state.currentPage || 1
      expandedGroups.value = state.expandedGroups || {}
      
      // Восстанавливаем данные фильтров
      if (state.dishTypesOptions) dishTypesOptions.value = state.dishTypesOptions
      if (state.ingredientsTree) ingredientsTree.value = state.ingredientsTree
      if (state.micronutrientsOptions) micronutrientsOptions.value = state.micronutrientsOptions
      
      // Если данные фильтров восстановлены, отмечаем как загруженные
      if (dishTypesOptions.value.length || ingredientsTree.value.length || micronutrientsOptions.value.length) {
        isFiltersLoaded.value = true
      }
      
      // Восстанавливаем состояние фильтров
      if (state.isFiltersOpen !== undefined) {
        if (isMobileFilterView.value && state.isFiltersOpen === true) {
          isFiltersOpen.value = false
        } else {
          isFiltersOpen.value = state.isFiltersOpen
        }
      } else {
        isFiltersOpen.value = !isMobileFilterView.value
      }
      return true
    } catch (e) {
      console.error('Ошибка восстановления фильтров:', e)
      forceResetFiltersState()
    }
  }
  return false
}

onDeactivated(() => {
  scrollY.value = window.scrollY
  saveFiltersToSession()
})

onActivated(async () => {
  window.scrollTo(0, scrollY.value)
  
  // Проверяем состояние фильтров
  if (!dishTypesOptions.value.length && !ingredientsTree.value.length && !isFiltersLoading.value) {
    await fetchFilters()
  }
  
  const hasFilters = restoreFiltersFromSession()
  
  // Если после восстановления фильтры все еще пустые
  if (!dishTypesOptions.value.length && !ingredientsTree.value.length) {
    forceResetFiltersState()
    await fetchFilters()
  }
  
  if (!isFirstActivation && hasFilters) {
    await fetchItems()
  } else if (isFirstActivation) {
    isFirstActivation = false
  } else {
    await fetchItems()
  }
})

onMounted(async () => {
  checkMobileView()
  window.addEventListener('resize', handleResize)
  
  if (route.query.select === 'true') {
    isSelectionMode.value = true
    const encodedTitle = route.query.mealTitle || 'приёма пищи'
    selectionMealTitle.value = decodeURIComponent(encodedTitle)
    selectionMealId.value = route.query.mealId ? parseInt(route.query.mealId) : null
    selectionRationId.value = route.query.rationId ? parseInt(route.query.rationId) : null
  }
  
  // Пытаемся восстановить состояние
  const hasFilters = restoreFiltersFromSession()
  
  // Проверяем, есть ли данные фильтров
  const hasData = dishTypesOptions.value.length > 0 || 
                  ingredientsTree.value.length > 0 || 
                  micronutrientsOptions.value.length > 0
  
  // Если данных нет - принудительно загружаем
  if (!hasData) {
    console.log('Данные фильтров не найдены, выполняем принудительную загрузку')
    forceResetFiltersState()
    await fetchFilters()
  }
  
  // Загружаем блюда
  if (hasFilters && isFiltersLoaded.value) {
    await fetchItems()
  } else {
    await fetchItems()
  }
  
  isFirstActivation = false
  isInitialLoad.value = false
})
</script>

<template>
<div class="catalog-page">
  <div v-if="isSelectionMode" class="selection-mode-wrapper">
    <div class="selection-mode-bar">
      <button @click="cancelSelection" class="mode-back-btn">← Назад</button>
      <span class="mode-title">Выбор блюда для <strong>{{ decodeURIComponent(selectionMealTitle) }}</strong></span>
      <div class="mode-spacer"></div>
    </div>
  </div>

  <div class="search-container">
    <div class="inputWithIcon" :class="{ darkItem: isDark }">
      <i class="fa fa-search"></i>
      <input 
        v-model="searchQuery" 
        placeholder="Введите название блюда..." 
        :class="{ darkInput: isDark }"
        @input="onSearchInput"
        @keyup.enter="applyFilters"
      >
    </div>
  </div>

  <div class="main-layout">
    <aside class="filters-sidebar" :class="{ darkItem: isDark }">
      <!-- Шапка с кнопкой переключения (видна на узких экранах) -->
      <div class="filters-header-mobile" @click="toggleFilters">
        <h3>Фильтры</h3>
        <button class="toggle-filters-btn" :class="{ rotated: isFiltersOpen }">
          <i class="fa fa-chevron-down"></i>
        </button>
      </div>
      
      <!-- Содержимое фильтров -->
      <div class="filters-content" v-if="isFiltersOpen">
        <!-- Показываем загрузку, если данные еще не загружены -->
        <div v-if="isFiltersLoading || !isFiltersLoaded" class="filters-loading">
          <div class="loader-small"></div>
          <span>Загрузка фильтров...</span>
        </div>
        
        <!-- Основное содержимое фильтров -->
        <template v-else>
          <div v-if="!dishTypesOptions.length && !ingredientsTree.length && !micronutrientsOptions.length" class="filters-loading">
            <span>Нет данных для фильтров</span>
            <button @click="fetchFilters" class="retry-btn">Обновить</button>
          </div>
          
          <template v-else>
            <div class="filter-section">
              <h4>Приём пищи</h4>
              <div class="filter-options">
                <label v-for="meal in mealTimesOptions" :key="meal.id" class="filter-label">
                  <input type="checkbox" :value="meal.id" :checked="selectedMealTimes.includes(meal.id)" @change="toggleMealTime(meal.id)">
                  <span>{{ meal.name }}</span>
                </label>
              </div>
            </div>

            <div class="filter-section" v-if="dishTypesOptions.length">
              <h4>Виды блюда</h4>
              <div class="filter-options">
                <label v-for="type in dishTypesOptions" :key="type.id" class="filter-label">
                  <input type="checkbox" :value="type.id" :checked="selectedDishTypes.includes(type.id)" @change="toggleDishType(type.id)">
                  <span>{{ type.name }}</span>
                </label>
              </div>
            </div>

            <div class="filter-section" v-if="ingredientsTree.length">
              <h4>Ингредиенты</h4>
              <div class="filter-options tree-view">
                <template v-for="node in ingredientsTree" :key="node.id">
                  <div class="ingredient-group">
                    <div class="ingredient-header">
                      <label class="filter-label">
                        <input 
                          type="checkbox" 
                          :value="node.id" 
                          :checked="selectedIngredients.includes(node.id)" 
                          @change="toggleParentIngredient(node.id, node.children)"
                        >
                        <span>{{ node.name }}</span>
                      </label>
                      <button 
                        v-if="node.children && node.children.length" 
                        class="expand-btn" 
                        :class="{ expanded: expandedGroups[node.id] }"
                        @click="toggleGroup(node.id)"
                      >
                        {{ expandedGroups[node.id] ? '▲' : '▼' }}
                      </button>
                    </div>
                    <div v-if="node.children && node.children.length && expandedGroups[node.id]" class="ingredient-children">
                      <div v-for="child in node.children" :key="child.id" class="ingredient-group">
                        <div class="ingredient-header" :style="{ marginLeft: '16px' }">
                          <label class="filter-label">
                            <input 
                              type="checkbox" 
                              :value="child.id" 
                              :checked="selectedIngredients.includes(child.id)" 
                              @change="toggleChildIngredient(child.id, node.id, node.children)"
                            >
                            <span>{{ child.name }}</span>
                          </label>
                        </div>
                      </div>
                    </div>
                  </div>
                </template>
              </div>
            </div>

            <div class="filter-section">
              <label class="filter-label high-protein-label">
                <input type="checkbox" v-model="highProtein" @change="applyFilters">
                <span>Высокобелковые</span>
              </label>
            </div>

            <div class="filter-section" v-if="micronutrientsOptions.length">
              <h4>Микронутриенты</h4>
              <div class="filter-options micronutrients-list">
                <label v-for="nutrient in micronutrientsOptions" :key="nutrient.id" class="filter-label">
                  <input 
                    type="radio" 
                    name="micronutrient"
                    :value="nutrient.id" 
                    :checked="selectedMicronutrient === nutrient.id" 
                    @change="setMicronutrient(nutrient.id)"
                  >
                  <span>{{ nutrient.name }}</span>
                </label>
                <label v-if="selectedMicronutrient" class="filter-label clear-micronutrient">
                  <input type="radio" name="micronutrient" :checked="false" @change="setMicronutrient(null)">
                  <span>✖ Очистить выбор</span>
                </label>
              </div>
            </div>

            <div class="filter-section">
              <h4>Ккал</h4>
              <div class="calories-range">
                <input type="number" v-model="caloriesFrom" placeholder="от" @change="applyFilters" :class="{ darkInput: isDark }">
                <span>—</span>
                <input type="number" v-model="caloriesTo" placeholder="до" @change="applyFilters" :class="{ darkInput: isDark }">
              </div>
            </div>

            <button class="reset-btn" @click="resetFilters" :class="{ darkItem: isDark }">Сбросить фильтры</button>
          </template>
        </template>
      </div>
    </aside>

    <div class="catalog-section">
      <div v-if="isLoading" class="loading-state">
        <div class="loader"></div>
        <span>Загрузка блюд...</span>
      </div>

      <div v-else-if="hasNoResults" class="empty-state">
        <div class="empty-icon">🍽️</div>
        <h3 class="empty-title">Ничего не найдено</h3>
        <p class="empty-description">Попробуйте изменить параметры поиска или сбросить фильтры</p>
        <button class="reset-filters-btn" @click="resetFilters">Сбросить фильтры</button>
      </div>

      <div v-else class="wrapper">
        <div 
          v-for="item in items" 
          :key="item.id"
          class="dish-card-wrapper"
        >
          <Card 
            :class="{ darkItem: isDark }" 
            :id="item.id" 
            :name="item.name" 
            :proteins="item.proteins" 
            :fats="item.fats" 
            :carbs="item.carbs" 
            :mass="item.mass" 
            :calories="item.calories" 
            :is-dark="isDark"
            @click="goToDish(item.id)" 
          />
          <button 
            v-if="isSelectionMode"
            class="select-dish-btn"
            @click.stop="selectAndReturn(item)"
          >
            ВЫБРАТЬ
          </button>
        </div>
      </div>

      <div v-if="!isLoading && total > 0" class="pagination">
        <button @click="goToPage(currentPage - 1)" :disabled="currentPage === 1" :class="{ darkItem: isDark }">← Предыдущая</button>
        <div class="page-numbers">
          <button 
            v-for="pageNum in Math.min(5, lastPage)" 
            :key="pageNum" 
            @click="goToPage(pageNum)" 
            :class="{ active: pageNum === currentPage, darkItem: isDark }"
          >
            {{ pageNum }}
          </button>
        </div>
        <button @click="goToPage(currentPage + 1)" :disabled="currentPage === lastPage" :class="{ darkItem: isDark }">Следующая →</button>
      </div>
    </div>
  </div>
</div>
</template>

<style scoped>
.catalog-page {
  min-height: calc(100vh - 70px);
}

.selection-mode-wrapper {
  display: flex;
  justify-content: center;
  padding: 12px 20px 0 20px;
}

.selection-mode-bar {
  display: inline-flex;
  align-items: center;
  gap: 24px;
  background: #4C9E2C;
  padding: 6px 18px;
  border-radius: 40px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.dark .selection-mode-bar {
  background: #3d7e23;
}

.mode-back-btn {
  background: rgba(255,255,255,0.2);
  border: none;
  padding: 4px 12px;
  border-radius: 30px;
  color: white;
  cursor: pointer;
  font-size: 13px;
  font-weight: 500;
  transition: all 0.2s;
}

.mode-back-btn:hover {
  background: rgba(255,255,255,0.35);
  transform: translateY(-1px);
}

.mode-title {
  font-size: 14px;
  color: white;
  white-space: nowrap;
}

.mode-title strong {
  background: rgba(0,0,0,0.2);
  padding: 3px 10px;
  border-radius: 30px;
  margin-left: 6px;
  font-size: 13px;
}

.mode-spacer {
  width: 55px;
}

.search-container {
  display: flex;
  justify-content: center;
  padding: 15px 0 25px;
}

.inputWithIcon {
  width: 50%;
  color: grey;
  padding-left: 2rem;
  height: 46px;
  display: flex;
  align-items: center;
  background: white;
  box-shadow: 0 0 1vh rgba(136, 136, 136, .2);
  border-radius: 8px;
  gap: 12px;
}

.inputWithIcon i {
  margin-right: 1rem;
  font-size: 18px;
}

.inputWithIcon input {
  margin-left: 0;
  border: none;
  background: transparent;
  width: 100%;
  font-size: 15px;
}

.inputWithIcon input:focus {
  outline: none;
}

.main-layout {
  display: flex;
  gap: 2rem;
  padding: 0 2rem;
}

.filters-sidebar {
  width: 260px;
  background: white;
  border-radius: 8px;
  padding: 1.5rem;
  box-shadow: 0 0 1vh rgba(136, 136, 136, .2);
  position: sticky;
  top: 90px;
  max-height: calc(100vh - 100px);
  overflow-y: auto;
  transition: all 0.3s ease;
}

.catalog-section {
  flex: 1;
  min-width: 0;
}

.filters-sidebar h3 {
  margin-bottom: 1.2rem;
  font-size: 1.1rem;
}

.filter-section {
  margin-bottom: 1.2rem;
  border-bottom: 1px solid #e0e0e0;
  padding-bottom: 0.8rem;
}

.filter-section h4 {
  margin-bottom: 0.6rem;
  font-size: 0.9rem;
}

.filter-options {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.filter-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  font-size: 0.85rem;
}

.filter-label input {
  width: 15px;
  height: 15px;
  cursor: pointer;
}

.high-protein-label {
  padding: 6px 0;
  font-weight: 500;
}

.micronutrients-list {
  max-height: 200px;
  overflow-y: auto;
}

.clear-micronutrient {
  margin-top: 6px;
  padding-top: 6px;
  border-top: 1px solid #e0e0e0;
  color: #f44336;
}

.ingredient-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.expand-btn {
  background: none;
  border: none;
  cursor: pointer;
  font-size: 10px;
  color: #666;
  transition: transform 0.2s;
}

.expand-btn.expanded {
  transform: rotate(180deg);
}

.ingredient-children {
  margin-left: 20px;
}

.calories-range {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}

.calories-range input {
  width: 70px;
  padding: 0.4rem;
  border: 1px solid #ddd;
  border-radius: 4px;
}

.reset-btn {
  width: 100%;
  padding: 0.6rem;
  background: #f0f0f0;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  margin-top: 1rem;
}

/* =========================
   КАРТОЧКИ
   ========================= */

.wrapper {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.dish-card-wrapper {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-width: 0;
}

.dish-card-wrapper .card {
  flex: 1;
  height: auto;
}

.select-dish-btn {
  width: 100%;
  margin-top: 12px;
  padding: 10px 12px;
  background: #4C9E2C;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  text-align: center;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.select-dish-btn:hover {
  background: #3d7e23;
  transform: translateY(-1px);
}

.select-dish-btn:active {
  transform: translateY(0);
}

/* =========================
   ПАГИНАЦИЯ
   ========================= */

.pagination {
  display: flex;
  justify-content: center;
  gap: 1rem;
  padding: 1.5rem 0;
}

.pagination button {
  padding: 0.4rem 1rem;
  border: none;
  background: white;
  box-shadow: 0 0 1vh rgba(136, 136, 136, .2);
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
}

.pagination button:hover:not(:disabled) {
  background: #f0f0f0;
}

.page-numbers {
  display: flex;
  gap: 0.5rem;
}

.page-numbers button.active {
  background: #4C9E2C;
  color: white;
}

.dark .pagination button {
  background: hsl(207, 26%, 22%);
  color: white;
  border: 1px solid rgba(255,255,255,0.1);
}

.dark .pagination button:hover:not(:disabled) {
  background: hsl(207, 26%, 28%);
}

.dark .page-numbers button.active {
  background: #4C9E2C !important;
  color: white;
}

/* =========================
   ЗАГРУЗКА
   ========================= */

.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 16px;
  padding: 60px 20px;
  color: #666;
}

.dark .loading-state {
  color: #ccc;
}

.loader {
  width: 48px;
  height: 48px;
  border: 3px solid #e0e0e0;
  border-top-color: #4C9E2C;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

.filters-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 20px 0;
  gap: 10px;
  color: #666;
}

.loader-small {
  width: 30px;
  height: 30px;
  border: 2px solid #e0e0e0;
  border-top-color: #4C9E2C;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

.retry-btn {
  padding: 6px 16px;
  background: #4C9E2C;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* =========================
   ПУСТОЙ РЕЗУЛЬТАТ
   ========================= */

.empty-state {
  text-align: center;
  padding: 80px 20px;
}

.empty-icon {
  font-size: 64px;
  margin-bottom: 20px;
  opacity: 0.5;
}

.empty-title {
  font-size: 24px;
  font-weight: 600;
  margin-bottom: 12px;
  color: #1a1a1a;
}

.dark .empty-title {
  color: #ffffff;
}

.empty-description {
  font-size: 16px;
  color: #666;
  margin-bottom: 24px;
}

.dark .empty-description {
  color: #aaa;
}

.reset-filters-btn {
  background: #4C9E2C;
  color: white;
  border: none;
  border-radius: 30px;
  padding: 10px 24px;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s;
}

.reset-filters-btn:hover {
  background: #3d7e23;
  transform: translateY(-2px);
}

/* =========================
   DARK MODE
   ========================= */

.darkItem {
  background: hsl(209, 23%, 22%) !important;
  color: white !important;
}

.darkInput::placeholder {
  color: rgba(255, 255, 255, 0.6);
}

.darkInput {
  color: white;
}

.darkItem .calories-range input {
  background: hsl(209, 23%, 22%);
  border-color: #4a5b6e;
  color: white;
}

/* =========================
   МОБИЛЬНАЯ ШАПКА ФИЛЬТРОВ
   ========================= */

.filters-header-mobile {
  display: none;
  justify-content: space-between;
  align-items: center;
  cursor: pointer;
  user-select: none;
  padding: 0 0 8px 0;
  margin-bottom: 12px;
}

.toggle-filters-btn {
  background: none;
  border: none;
  cursor: pointer;
  font-size: 18px;
  color: inherit;
  transition: transform 0.2s ease;
  padding: 0;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.toggle-filters-btn.rotated i {
  transform: rotate(180deg);
}

.toggle-filters-btn i {
  transition: transform 0.2s ease;
  display: inline-block;
}

/* =========================
   АДАПТИВНОСТЬ
   ========================= */

/* Планшет / небольшое окно:
   2 карточки */
@media (max-width: 1200px) {
  .wrapper {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1.2rem;
  }

  .filters-header-mobile {
    display: flex;
  }

  .filters-sidebar {
    width: 100%;
    position: static;
    margin-bottom: 20px;
  }

  .main-layout {
    flex-direction: column;
  }

  .inputWithIcon {
    width: 70%;
  }

  .select-dish-btn {
    padding: 8px 10px;
    font-size: 13px;
  }
}

/* Телефон:
   1 карточка */
@media (max-width: 768px) {
  .wrapper {
    grid-template-columns: 1fr;
    gap: 1rem;
  }

  .inputWithIcon {
    width: 90%;
  }

  .main-layout {
    padding: 0 1rem;
  }

  .selection-mode-bar {
    padding: 4px 12px;
    gap: 12px;
  }

  .mode-back-btn {
    padding: 3px 8px;
    font-size: 11px;
  }

  .mode-title {
    font-size: 10px;
  }

  .mode-title strong {
    font-size: 10px;
    padding: 2px 6px;
  }

  .mode-spacer {
    width: 35px;
  }

  .select-dish-btn {
    padding: 8px 12px;
    font-size: 13px;
    margin-top: 10px;
    border-radius: 8px;
  }

  .empty-title {
    font-size: 20px;
  }

  .empty-description {
    font-size: 14px;
  }
}

/* Маленький телефон */
@media (max-width: 480px) {
  .catalog-page {
    padding-bottom: 20px;
  }

  .search-container {
    padding: 10px 0 15px;
  }

  .inputWithIcon {
    height: 40px;
    padding-left: 1.5rem;
  }

  .inputWithIcon i {
    font-size: 14px;
  }

  .inputWithIcon input {
    font-size: 13px;
  }

  .filters-sidebar {
    padding: 1rem;
  }

  .filter-section {
    margin-bottom: 0.8rem;
  }

  .select-dish-btn {
    padding: 6px 10px;
    font-size: 12px;
    margin-top: 8px;
    border-radius: 8px;
  }

  .calories-range input {
    width: 60px;
    padding: 0.3rem;
  }

  .reset-btn {
    padding: 0.5rem;
    font-size: 13px;
  }

  .pagination button {
    padding: 0.3rem 0.8rem;
    font-size: 12px;
  }
}

.loading {
  text-align: center;
  padding: 3rem;
}

input:focus,
button:focus {
  outline: none;
}
</style>