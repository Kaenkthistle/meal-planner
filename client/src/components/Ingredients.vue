<template>
  <div v-if="visible" class="modal-overlay" @click.self="close">
    <div class="modal-content" :class="{ dark: isDark }">
      <div class="modal-header">
        <h2>Управление ингредиентами</h2>
        <button class="close-btn" @click="close">✕</button>
      </div>
      
      <div class="modal-body">
        <p class="modal-description">
          Выберите ингредиенты, блюда с которыми не должны использоваться при автоматической генерации рациона
        </p>
        
        <div class="ingredients-tree">
          <div v-for="item in ingredientsTree" :key="item.id" class="tree-node">
            <div class="node-header">
              <label class="node-label">
                <input 
                  type="checkbox" 
                  :value="item.id"
                  :checked="isChecked(item.id)"
                  @change="toggleParent(item.id, item.children)"
                  class="node-checkbox"
                >
                <span class="node-name">{{ item.name }}</span>
              </label>
              <button 
                v-if="item.children && item.children.length"
                class="expand-btn"
                :class="{ expanded: expandedNodes.includes(item.id) }"
                @click="toggleExpand(item.id)"
              >
                {{ expandedNodes.includes(item.id) ? '▲' : '▼' }}
              </button>
            </div>
            
            <div v-if="item.children && item.children.length && expandedNodes.includes(item.id)" class="node-children">
              <div v-for="child in item.children" :key="child.id" class="child-node">
                <label class="node-label">
                  <input 
                    type="checkbox" 
                    :value="child.id"
                    :checked="isChecked(child.id)"
                    @change="toggleChild(child.id, item.id, item.children)"
                    class="node-checkbox"
                  >
                  <span class="node-name">{{ child.name }}</span>
                </label>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="modal-footer">
        <button class="cancel-modal-btn" @click="close">Отмена</button>
        <button class="save-btn" @click="saveChanges">Сохранить</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'
import { API_ENDPOINTS } from '@/config/api'

const props = defineProps({
  visible: Boolean,
  isDark: Boolean,
  currentExcluded: Array
})

const emit = defineEmits(['close', 'save'])

const ingredientsTree = ref([])
const selectedIngredients = ref(new Set())
const expandedNodes = ref([])
const isLoading = ref(false)

// Загрузка дерева ингредиентов
const loadIngredients = async () => {
  try {
    isLoading.value = true
    const response = await axios.get(API_ENDPOINTS.GENERATE_DIET_FILTERS || '/api/generator/filters')
    ingredientsTree.value = response.data.ingredients || []
  } catch (error) {
    console.error('Ошибка загрузки ингредиентов:', error)
  } finally {
    isLoading.value = false
  }
}

// Проверка, выбран ли ингредиент
const isChecked = (id) => {
  return selectedIngredients.value.has(id)
}

// Переключение родительского чекбокса
const toggleParent = (parentId, children) => {
  const newState = !selectedIngredients.value.has(parentId)
  
  if (newState) {
    selectedIngredients.value.add(parentId)
    if (children && children.length) {
      children.forEach(child => {
        selectedIngredients.value.add(child.id)
      })
    }
  } else {
    selectedIngredients.value.delete(parentId)
  }
}

// Переключение дочернего чекбокса
const toggleChild = (childId, parentId, siblings) => {
  const newState = !selectedIngredients.value.has(childId)
  
  if (newState) {
    selectedIngredients.value.add(childId)
  } else {
    selectedIngredients.value.delete(childId)
  }
  
  const allChildrenSelected = siblings.every(child => selectedIngredients.value.has(child.id))
  
  if (allChildrenSelected) {
    selectedIngredients.value.add(parentId)
  } else {
    selectedIngredients.value.delete(parentId)
  }
}

// Переключение раскрытия группы
const toggleExpand = (id) => {
  if (expandedNodes.value.includes(id)) {
    expandedNodes.value = expandedNodes.value.filter(nodeId => nodeId !== id)
  } else {
    expandedNodes.value.push(id)
  }
}

// Сохранение изменений
const saveChanges = () => {
  const excludedIds = Array.from(selectedIngredients.value)
  emit('save', excludedIds)
  close()
}

// Закрытие модалки
const close = () => {
  emit('close')
}

// Синхронизация выбранных ингредиентов при открытии
const syncSelectedIngredients = () => {
  selectedIngredients.value.clear()
  if (props.currentExcluded && props.currentExcluded.length) {
    props.currentExcluded.forEach(id => selectedIngredients.value.add(id))
  }
  
  expandedNodes.value = []
  if (ingredientsTree.value.length) {
    ingredientsTree.value.forEach(node => {
      if (node.children && node.children.length) {
        const hasSelectedChild = node.children.some(child => selectedIngredients.value.has(child.id))
        if (hasSelectedChild) {
          expandedNodes.value.push(node.id)
        }
      }
    })
  }
}

// Следим за открытием модалки
watch(() => props.visible, (newVal) => {
  if (newVal) {
    loadIngredients().then(() => {
      syncSelectedIngredients()
    })
  }
})
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
  max-width: 500px;
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

.modal-description {
  font-size: 14px;
  color: #666;
  margin-bottom: 20px;
}

.dark .modal-description {
  color: #ccc;
}

.ingredients-tree {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.tree-node {
  margin-bottom: 4px;
}

.node-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 0;
  border-bottom: 1px solid #f0f0f0;
}

.dark .node-header {
  border-bottom-color: rgba(255, 255, 255, 0.1);
}

.node-label {
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
  flex: 1;
}

.node-checkbox {
  width: 18px;
  height: 18px;
  cursor: pointer;
  accent-color: #4C9E2C;
}

.node-name {
  font-size: 15px;
  font-weight: 500;
}

.dark .node-name {
  color: white;
}

.expand-btn {
  background: none;
  border: none;
  cursor: pointer;
  font-size: 12px;
  color: #666;
  width: 28px;
  height: 28px;
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}

.expand-btn:hover {
  background: rgba(0, 0, 0, 0.05);
}

.dark .expand-btn {
  color: #ccc;
}

.dark .expand-btn:hover {
  background: rgba(255, 255, 255, 0.1);
}

.node-children {
  margin-left: 28px;
  padding-left: 8px;
  border-left: 1px solid #e0e0e0;
}

.dark .node-children {
  border-left-color: rgba(255, 255, 255, 0.1);
}

.child-node {
  padding: 8px 0;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 16px 24px;
  border-top: 1px solid #e0e0e0;
}

.dark .modal-footer {
  border-top-color: rgba(255, 255, 255, 0.1);
}

.cancel-modal-btn {
  padding: 8px 20px;
  background: transparent;
  border: 1px solid #ccc;
  border-radius: 8px;
  cursor: pointer;
  font-size: 14px;
  transition: all 0.2s;
}

.cancel-modal-btn:hover {
  background: rgba(0, 0, 0, 0.05);
}

.dark .cancel-modal-btn {
  border-color: #555;
  color: white;
}

.dark .cancel-modal-btn:hover {
  background: rgba(255, 255, 255, 0.1);
}

.save-btn {
  padding: 8px 24px;
  background: #4C9E2C;
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-size: 14px;
  font-weight: 500;
  transition: all 0.2s;
}

.save-btn:hover {
  background: #3d7e23;
  transform: translateY(-1px);
}

@media (max-width: 768px) {
  .modal-content {
    max-width: 95%;
    max-height: 90vh;
  }
  
  .modal-header h2 {
    font-size: 18px;
  }
  
  .node-name {
    font-size: 14px;
  }
  
  .node-children {
    margin-left: 20px;
  }
}
</style>