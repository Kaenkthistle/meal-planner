<template>
  <div v-if="visible" class="modal-overlay" @click.self="close">
    <div class="modal-content" :class="{ dark: isDark }">
      <div class="modal-header">
        <h2>📊 Рекомендации по микронутриентам</h2>
        <button class="close-btn" @click="close">✕</button>
      </div>
      
      <div class="modal-body">
        <!-- Состояние загрузки -->
        <div v-if="isLoading" class="loading-state">
          <div class="loader"></div>
          <span>Анализируем рацион...</span>
        </div>

        <!-- Если всё в норме -->
        <div v-else-if="lowMicronutrients.length === 0 && goodMicronutrients.length === 0 && !isLoading" class="empty-state">
          <span class="empty-icon">🎉</span>
          <span>Отлично! Все микронутриенты в норме.</span>
        </div>

        <!-- Если есть данные -->
        <template v-else>
          <!-- Переключатель между дефицитом и достатком -->
          <div v-if="goodMicronutrients.length > 0" class="tabs">
            <button 
              :class="['tab-btn', { active: activeTab === 'low' }]"
              @click="activeTab = 'low'"
            >
              ⚠️ В рационе не хватает
              <span class="badge">{{ lowMicronutrients.length }}</span>
            </button>
            <button 
              :class="['tab-btn', { active: activeTab === 'good' }]"
              @click="activeTab = 'good'"
            >
              ✅ Рацион богат
              <span class="badge good">{{ goodMicronutrients.length }}</span>
            </button>
          </div>

          <!-- Описание -->
          <p class="modal-description">
            <template v-if="activeTab === 'low' && lowMicronutrients.length > 0">
              В средний ежедневный рацион рекомендуется включать больше продуктов, богатых следующими веществами:
            </template>
            <template v-else-if="activeTab === 'good' && goodMicronutrients.length > 0">
              Ваш составленный рацион богат следующими полезными веществами:
            </template>
          </p>
          
          <!-- Список микронутриентов в порядке от сервера -->
          <div class="nutrients-grid">
            <div 
              v-for="nutrient in currentNutrients" 
              :key="nutrient.id" 
              class="nutrient-card" 
              :class="{ dark: isDark, good: activeTab === 'good' }"
            >
              <div class="nutrient-icon" :style="{ background: getNutrientColor(nutrient.id) }">
                <span class="nutrient-emoji">{{ getNutrientEmoji(nutrient.id) }}</span>
              </div>
              <div class="nutrient-info">
                <div class="nutrient-name">{{ nutrient.name }}</div>
                <div class="nutrient-sources">{{ nutrient.sources }}</div>
                <div v-if="activeTab === 'low'" class="nutrient-percent">
                  Покрыто: {{ nutrient.percent }}%
                </div>
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { API_ENDPOINTS } from '@/config/api'
import micronutrientsData from '@/data/micronutrients_info.json'

const props = defineProps({
  visible: Boolean,
  isDark: Boolean,
  dishes: Array
})

const emit = defineEmits(['close'])

const activeTab = ref('low')
const lowMicronutrients = ref([])
const goodMicronutrients = ref([])
const micronutrientsInfo = ref(micronutrientsData.micronutrients)
const isLoading = ref(false)

// Эмодзи для микронутриентов
const getNutrientEmoji = (id) => {
  const emojis = {
    1: '☀️',
    2: '🥩',
    3: '🥬',
    4: '🍊',
    5: '🥕',
    6: '🥩',
    7: '🥛',
    8: '🥜',
    9: '🦪',
    10: '🍌'
  }
  return emojis[id] || '💊'
}

// Цвета для микронутриентов
const getNutrientColor = (id) => {
  const colors = {
    1: '#FFF3E0',
    2: '#FFEBEE',
    3: '#E8F5E9',
    4: '#FFF9C4',
    5: '#FFE0B2',
    6: '#FBE9E7',
    7: '#E3F2FD',
    8: '#F3E5F5',
    9: '#E0F7FA',
    10: '#D1C4E9'
  }
  return colors[id] || '#f5f5f5'
}

// Текущие микронутриенты в ПОРЯДКЕ от сервера
const currentNutrients = computed(() => {
  // Берём данные с сервера в том порядке, как они пришли
  const serverData = activeTab.value === 'low' 
    ? lowMicronutrients.value 
    : goodMicronutrients.value
  
  // Для каждого микронутриента из ответа сервера добавляем информацию из JSON
  return serverData.map(serverNutrient => {
    const info = micronutrientsInfo.value.find(n => n.id === serverNutrient.micronutrient_id)
    return {
      id: serverNutrient.micronutrient_id,
      name: info?.name || 'Неизвестный витамин',
      sources: info?.sources || 'Информация отсутствует',
      percent: serverNutrient.percent
    }
  })
})

// Проверка микронутриентов на бэке
const checkMicronutrients = async () => {
  if (!props.dishes || props.dishes.length === 0) {
    lowMicronutrients.value = []
    goodMicronutrients.value = []
    return
  }
  
  isLoading.value = true
  
  try {
    const requestBody = { dishes: props.dishes }
    
    const response = await fetch(API_ENDPOINTS.MICRONUTRIENTS_CHECK, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(requestBody)
    })
    
    const result = await response.json()
    lowMicronutrients.value = result.low_micronutrients || []
    goodMicronutrients.value = result.good_micronutrients || []
    
    if (lowMicronutrients.value.length > 0) {
      activeTab.value = 'low'
    } else if (goodMicronutrients.value.length > 0) {
      activeTab.value = 'good'
    }
  } catch (error) {
    console.error('Ошибка проверки микронутриентов:', error)
    lowMicronutrients.value = []
    goodMicronutrients.value = []
  } finally {
    isLoading.value = false
  }
}

// Следим за открытием модалки
watch(() => props.visible, (newVal) => {
  if (newVal) {
    checkMicronutrients()
  }
})

const close = () => {
  emit('close')
}
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.7);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  backdrop-filter: blur(4px);
}

.modal-content {
  background: white;
  border-radius: 16px;
  width: 90%;
  max-width: 700px;
  max-height: 85vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
}

.modal-content.dark {
  background: hsl(207, 26%, 17%);
  color: white;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid #e0e0e0;
}

.dark .modal-header {
  border-bottom-color: rgba(255, 255, 255, 0.1);
}

.modal-header h2 {
  margin: 0;
  font-size: 20px;
}

.close-btn {
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}

.close-btn:hover {
  background: rgba(0, 0, 0, 0.1);
}

.dark .close-btn {
  color: white;
}

.dark .close-btn:hover {
  background: rgba(255, 255, 255, 0.1);
}

.modal-body {
  flex: 1;
  overflow-y: auto;
  padding: 20px 24px;
}

/* Состояние загрузки */
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
  width: 40px;
  height: 40px;
  border: 3px solid #e0e0e0;
  border-top-color: #4C9E2C;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Табы */
.tabs {
  display: flex;
  gap: 12px;
  margin-bottom: 20px;
}

.tab-btn {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 10px 16px;
  background: #f0f0f0;
  border: none;
  border-radius: 10px;
  cursor: pointer;
  font-size: 14px;
  font-weight: 500;
  transition: all 0.2s;
}

.dark .tab-btn {
  background: hsl(207, 26%, 22%);
  color: white;
}

.tab-btn.active {
  background: #4C9E2C;
  color: white;
}

.tab-btn .badge {
  background: rgba(0, 0, 0, 0.2);
  padding: 2px 8px;
  border-radius: 20px;
  font-size: 12px;
}

.tab-btn .badge.good {
  background: rgba(255, 255, 255, 0.2);
}

.modal-description {
  margin-bottom: 20px;
  font-size: 14px;
  color: #666;
}

.dark .modal-description {
  color: #ccc;
}

/* Пустое состояние */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 60px 20px;
  text-align: center;
}

.empty-icon {
  font-size: 48px;
}

.empty-state span:last-child {
  font-size: 16px;
  color: #4C9E2C;
  font-weight: 500;
}

.dark .empty-state span:last-child {
  color: #6abf4a;
}

/* Сетка карточек */
.nutrients-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 12px;
}

.nutrient-card {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px;
  background: #f9f9f9;
  border-radius: 12px;
  transition: all 0.2s;
}

.nutrient-card.dark {
  background: hsl(207, 26%, 22%);
}

.nutrient-card.good {
  background: rgba(76, 158, 44, 0.1);
  border-left: 3px solid #4C9E2C;
}

.dark .nutrient-card.good {
  background: rgba(76, 158, 44, 0.15);
}

.nutrient-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.nutrient-emoji {
  font-size: 28px;
}

.nutrient-info {
  flex: 1;
}

.nutrient-name {
  font-weight: 600;
  font-size: 14px;
  margin-bottom: 4px;
}

.nutrient-sources {
  font-size: 11px;
  opacity: 0.7;
  margin-bottom: 4px;
}

.nutrient-percent {
  font-size: 11px;
  color: #ff9800;
  font-weight: 500;
}
</style>