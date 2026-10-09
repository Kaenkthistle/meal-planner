<template>
  <div class="rations-page" :class="{ dark: isDark }">
    <div class="rations-container">
      <div class="page-header">
        <h1>Мои рационы</h1>
        <button @click="createNewRation" class="create-btn" :class="{ darkItem: isDark }">
          + Создать новый рацион
        </button>
      </div>

      <div v-if="isLoading" class="loading-state">
        <div class="spinner"></div>
        <p>Загрузка рационов...</p>
      </div>

      <div v-else-if="rations.length === 0" class="empty-state" :class="{ darkItem: isDark }">
        <div class="empty-icon">📋</div>
        <h3>У вас пока нет сохранённых рационов</h3>
        <p>Создайте свой первый рацион в конструкторе меню</p>
        <button @click="createNewRation" class="empty-create-btn" :class="{ darkItem: isDark }">
          Создать рацион
        </button>
      </div>

      <div v-else class="rations-grid">
        <div 
          v-for="ration in rations" 
          :key="ration.id" 
          class="ration-card" 
          :class="{ darkItem: isDark }"
        >
          <div class="card-header">
            <div class="card-date">{{ formatDate(ration.created_date) }}</div>
            <div class="card-actions">
              <button 
                @click="editRation(ration.id)" 
                class="action-btn edit" 
                title="Редактировать"
              >
                ✏️
              </button>
              <button 
                @click="deleteRation(ration.id)" 
                class="action-btn delete" 
                title="Удалить"
              >
                🗑️
              </button>
            </div>
          </div>
          
          <h3 class="card-title">{{ ration.name }}</h3>
          
          <div class="card-stats">
            <div class="stat-item">
              <span class="stat-value">{{ roundNumber(ration.totals.proteins) }}</span>
              <span class="stat-label">Белки</span>
            </div>
            <div class="stat-item">
              <span class="stat-value">{{ roundNumber(ration.totals.fats) }}</span>
              <span class="stat-label">Жиры</span>
            </div>
            <div class="stat-item">
              <span class="stat-value">{{ roundNumber(ration.totals.carbohydrates) }}</span>
              <span class="stat-label">Углеводы</span>
            </div>
            <div class="stat-item">
              <span class="stat-value">{{ roundNumber(ration.totals.calories) }}</span>
              <span class="stat-label">ккал</span>
            </div>
          </div>
          
          <div class="card-meals" v-if="ration.preview_meals && ration.preview_meals.length">
            <div 
              v-for="meal in ration.preview_meals" 
              :key="meal.meal_type" 
              class="meal-preview"
            >
              <span class="meal-type">{{ meal.meal_type }}:</span>
              <span class="meal-dishes">{{ meal.dishes_string }}</span>
            </div>
          </div>
          
          <div class="card-footer">
            <span class="total-dishes">🍽️ {{ ration.total_dishes }} блюд</span>
            <button @click="viewRation(ration.id)" class="view-btn" :class="{ darkItem: isDark }">
              Подробнее →
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Модальное окно подтверждения удаления -->
    <div v-if="showDeleteModal" class="modal-overlay" @click.self="closeModal">
      <div class="modal-content" :class="{ darkItem: isDark }">
        <h3>Удалить рацион?</h3>
        <p>Вы уверены, что хотите удалить рацион <strong>"{{ deletingRationName }}"</strong>?</p>
        <p class="warning">Это действие нельзя отменить.</p>
        <div class="modal-buttons">
          <button @click="closeModal" class="cancel-btn">Отмена</button>
          <button @click="confirmDelete" class="confirm-btn">Удалить</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, inject, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useUserStore } from '../stores/userStore'
import { API_ENDPOINTS } from '@/config/api'

const router = useRouter()
const userStore = useUserStore()
const isDark = inject('isDark', ref(false))

const rations = ref([])
const isLoading = ref(false)
const showDeleteModal = ref(false)
const deletingRationId = ref(null)
const deletingRationName = ref('')

// Форматирование даты
function formatDate(dateString) {
  if (!dateString) return ''
  const date = new Date(dateString)
  return date.toLocaleDateString('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  })
}

// Округление чисел
function roundNumber(value) {
  return Math.round(value)
}

// Загрузка списка рационов
async function loadRations() {
  isLoading.value = true
  try {
    const token = userStore.token
    const response = await fetch(API_ENDPOINTS.RATIONS, {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
      }
    })
    
    if (!response.ok) {
      if (response.status === 401) {
        router.push('/auth')
        return
      }
      throw new Error('Ошибка загрузки рационов')
    }
    
    const result = await response.json()
    if (result.success) {
      rations.value = result.data
    }
  } catch (error) {
    console.error('Ошибка:', error)
  } finally {
    isLoading.value = false
  }
}

// Создать новый рацион
function createNewRation() {
  router.push('/constructor')
}

// Редактировать рацион
function editRation(rationId) {
  router.push(`/constructor?rationId=${rationId}`)
}

// Просмотреть рацион (подробно)
function viewRation(rationId) {
  router.push(`/constructor?rationId=${rationId}`)
}

// Удалить рацион
function deleteRation(rationId) {
  const ration = rations.value.find(r => r.id === rationId)
  if (ration) {
    deletingRationId.value = rationId
    deletingRationName.value = ration.name
    showDeleteModal.value = true
  }
}

// Подтверждение удаления
async function confirmDelete() {
  try {
    const token = userStore.token
    const response = await fetch(API_ENDPOINTS.RATION(deletingRationId.value), {
      method: 'DELETE',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
      }
    })
    
    if (!response.ok) throw new Error('Ошибка удаления')
    
    // Удаляем из списка
    rations.value = rations.value.filter(r => r.id !== deletingRationId.value)
    closeModal()
  } catch (error) {
    console.error('Ошибка:', error)
    alert('Не удалось удалить рацион')
  }
}

// Закрыть модальное окно
function closeModal() {
  showDeleteModal.value = false
  deletingRationId.value = null
  deletingRationName.value = ''
}

onMounted(() => {
  // Проверяем авторизацию
  if (!userStore.isAuthenticated) {
    router.push('/auth')
    return
  }
  loadRations()
})
</script>

<style scoped>
/* Стили остаются без изменений */
.rations-page {
  min-height: calc(100vh - 70px);
  background: #f5f5f5;
  padding: 40px 5%;
}

.dark.rations-page {
  background: hsl(207, 26%, 17%);
}

.rations-container {
  max-width: 1200px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 32px;
  flex-wrap: wrap;
  gap: 16px;
}

.page-header h1 {
  font-size: 28px;
  font-weight: 700;
  color: #333;
}

.dark .page-header h1 {
  color: white;
}

.create-btn {
  padding: 10px 20px;
  background: #4C9E2C;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
}

.create-btn:hover {
  background: #3d7e23;
  transform: translateY(-2px);
}

.loading-state {
  text-align: center;
  padding: 60px 20px;
  color: #666;
}

.dark .loading-state {
  color: #aaa;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 3px solid #ddd;
  border-top-color: #4C9E2C;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 16px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.empty-state {
  text-align: center;
  padding: 60px 20px;
  background: white;
  border-radius: 16px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.dark .empty-state {
  background: hsl(209, 23%, 22%);
  color: white;
}

.empty-icon {
  font-size: 64px;
  margin-bottom: 16px;
}

.empty-state h3 {
  font-size: 20px;
  margin-bottom: 8px;
}

.empty-state p {
  color: #666;
  margin-bottom: 24px;
}

.dark .empty-state p {
  color: #aaa;
}

.empty-create-btn {
  padding: 12px 24px;
  background: #4C9E2C;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
}

.empty-create-btn:hover {
  background: #3d7e23;
  transform: translateY(-2px);
}

.rations-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
  gap: 24px;
}

.ration-card {
  background: white;
  border-radius: 16px;
  padding: 20px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.05);
  transition: all 0.3s;
  display: flex;
  flex-direction: column;
}

.ration-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.dark .ration-card {
  background: hsl(209, 23%, 22%);
  color: white;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.card-date {
  font-size: 12px;
  color: #888;
  font-weight: 500;
}

.dark .card-date {
  color: #aaa;
}

.card-actions {
  display: flex;
  gap: 8px;
}

.action-btn {
  background: none;
  border: none;
  cursor: pointer;
  font-size: 16px;
  padding: 4px;
  border-radius: 4px;
  transition: all 0.2s;
}

.action-btn.edit:hover {
  background: rgba(76, 158, 44, 0.1);
  transform: scale(1.1);
}

.action-btn.delete:hover {
  background: rgba(244, 67, 54, 0.1);
  transform: scale(1.1);
}

.card-title {
  font-size: 18px;
  font-weight: 700;
  margin-bottom: 16px;
  color: #333;
}

.dark .card-title {
  color: white;
}

.card-stats {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
  margin-bottom: 20px;
  padding: 12px 0;
  border-top: 1px solid #eee;
  border-bottom: 1px solid #eee;
}

.dark .card-stats {
  border-color: rgba(255,255,255,0.1);
}

.stat-item {
  text-align: center;
}

.stat-value {
  display: block;
  font-size: 18px;
  font-weight: 700;
  color: #4C9E2C;
}

.dark .stat-value {
  color: #6abf4a;
}

.stat-label {
  font-size: 11px;
  color: #888;
}

.dark .stat-label {
  color: #aaa;
}

.card-meals {
  flex: 1;
  margin-bottom: 16px;
}

.meal-preview {
  margin-bottom: 8px;
  font-size: 13px;
  line-height: 1.4;
}

.meal-type {
  font-weight: 700;
  color: #4C9E2C;
}

.dark .meal-type {
  color: #6abf4a;
}

.meal-dishes {
  color: #555;
  margin-left: 4px;
}

.dark .meal-dishes {
  color: #ccc;
}

.card-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px solid #eee;
}

.dark .card-footer {
  border-color: rgba(255,255,255,0.1);
}

.total-dishes {
  font-size: 12px;
  color: #888;
}

.view-btn {
  padding: 6px 16px;
  background: transparent;
  border: 1px solid #4C9E2C;
  color: #4C9E2C;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
}

.view-btn:hover {
  background: #4C9E2C;
  color: white;
}

.dark .view-btn {
  border-color: #6abf4a;
  color: #6abf4a;
}

.dark .view-btn:hover {
  background: #6abf4a;
  color: white;
}

.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0,0,0,0.5);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 2000;
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

.cancel-btn {
  padding: 10px 20px;
  background: #e0e0e0;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.3s;
}

.cancel-btn:hover {
  background: #ccc;
}

.confirm-btn {
  padding: 10px 20px;
  background: #f44336;
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.3s;
}

.confirm-btn:hover {
  background: #d32f2f;
}

@media (max-width: 768px) {
  .rations-page {
    padding: 20px 15px;
  }
  
  .page-header {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .page-header h1 {
    font-size: 24px;
  }
  
  .rations-grid {
    grid-template-columns: 1fr;
  }
  
  .card-stats {
    gap: 8px;
  }
  
  .stat-value {
    font-size: 16px;
  }
  
  .card-title {
    font-size: 16px;
  }
  
  .meal-preview {
    font-size: 12px;
  }
}

@media (max-width: 480px) {
  .card-stats {
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
  }
}
</style>