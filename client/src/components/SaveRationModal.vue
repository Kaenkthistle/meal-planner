<template>
  <div v-if="visible" class="modal-overlay" @click.self="$emit('close')">
    <div class="modal-container" :class="{ darkItem: isDark }">
      <div class="modal-header">
        <h3>💾 Сохранить рацион</h3>
        <button class="close-btn" @click="$emit('close')">✕</button>
      </div>
      
      <div class="modal-body">
        <p>Придумайте название для вашего рациона:</p>
        
        <input 
          type="text" 
          v-model="localName"
          class="ration-name-input"
          :class="{ darkItem: isDark }"
          placeholder="Например: Мой идеальный рацион"
          maxlength="150"
          autofocus
        />
        
        <div class="char-counter">{{ localName.length }}/150</div>
        
        <div class="modal-info">
          <div class="info-item">
            <span>🍽️ Блюд:</span>
            <strong>{{ totalDishes }}</strong>
          </div>
          <div class="info-item">
            <span>🔥 Ккал:</span>
            <strong>{{ Math.round(totals.k) }}</strong>
          </div>
          <div class="info-item">
            <span>💪 Белки:</span>
            <strong>{{ Math.round(totals.p) }} г</strong>
          </div>
          <div class="info-item">
            <span>🧈 Жиры:</span>
            <strong>{{ Math.round(totals.f) }} г</strong>
          </div>
          <div class="info-item">
            <span>🍚 Углеводы:</span>
            <strong>{{ Math.round(totals.c) }} г</strong>
          </div>
        </div>
      </div>
      
      <div class="modal-footer">
        <button class="cancel-btn" @click="$emit('close')">Отмена</button>
        <button 
          class="save-btn" 
          @click="handleSave"
          :disabled="!localName.trim()"
        >
          Сохранить
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue'

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  isDark: {
    type: Boolean,
    default: false
  },
  currentName: {
    type: String,
    default: ''
  },
  totals: {
    type: Object,
    default: () => ({ k: 0, p: 0, f: 0, c: 0 })
  },
  totalDishes: {
    type: Number,
    default: 0
  }
})

const emit = defineEmits(['close', 'save'])

const localName = ref('')

watch(() => props.currentName, (newVal) => {
  localName.value = newVal
}, { immediate: true })

watch(() => props.visible, (newVal) => {
  if (newVal) {
    localName.value = props.currentName
  }
})

function handleSave() {
  if (localName.value.trim()) {
    emit('save', localName.value.trim())
  }
}
</script>

<style scoped>
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

.modal-container {
  background: white;
  border-radius: 20px;
  width: 90%;
  max-width: 450px;
  overflow: hidden;
  animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
  from {
    opacity: 0;
    transform: translateY(-30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid #eee;
}

.dark .modal-header {
  border-bottom-color: rgba(255, 255, 255, 0.1);
}

.modal-header h3 {
  font-size: 20px;
  font-weight: 700;
  margin: 0;
  color: inherit;
}

.close-btn {
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: #999;
  transition: all 0.2s;
  padding: 0;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
}

.close-btn:hover {
  background: rgba(0, 0, 0, 0.05);
  color: #333;
}

.dark .close-btn:hover {
  background: rgba(255, 255, 255, 0.1);
  color: white;
}

.modal-body {
  padding: 24px;
}

.modal-body p {
  margin-bottom: 12px;
  color: #555;
  font-size: 14px;
}

.dark .modal-body p {
  color: #ccc;
}

.ration-name-input {
  width: 100%;
  padding: 12px 16px;
  border: 1px solid #ddd;
  border-radius: 12px;
  font-size: 16px;
  transition: all 0.3s;
  background: white;
}

.dark .ration-name-input {
  background: hsl(207, 26%, 28%);
  border-color: rgba(255, 255, 255, 0.2);
  color: white;
}

.ration-name-input:focus {
  border-color: #4C9E2C;
  outline: none;
}

.ration-name-input::placeholder {
  color: #aaa;
}

.char-counter {
  text-align: right;
  font-size: 12px;
  color: #999;
  margin-top: 4px;
}

.modal-info {
  background: #f5f5f5;
  border-radius: 12px;
  padding: 16px;
  margin-top: 20px;
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}

.dark .modal-info {
  background: hsla(207, 26%, 25%, 0.5);
}

.info-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 14px;
}

.info-item span {
  color: #666;
}

.dark .info-item span {
  color: #aaa;
}

.info-item strong {
  color: #4C9E2C;
  font-size: 16px;
}

.dark .info-item strong {
  color: #6abf4a;
}

.modal-footer {
  display: flex;
  gap: 12px;
  padding: 20px 24px;
  border-top: 1px solid #eee;
}

.dark .modal-footer {
  border-top-color: rgba(255, 255, 255, 0.1);
}

.cancel-btn {
  flex: 1;
  padding: 12px;
  background: #e0e0e0;
  border: none;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
}

.cancel-btn:hover {
  background: #ccc;
}

.dark .cancel-btn {
  background: hsl(207, 26%, 28%);
  color: white;
}

.dark .cancel-btn:hover {
  background: hsl(207, 26%, 35%);
}

.save-btn {
  flex: 1;
  padding: 12px;
  background: #4C9E2C;
  color: white;
  border: none;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
}

.save-btn:hover:not(:disabled) {
  background: #3d7e23;
  transform: translateY(-1px);
}

.save-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

@media (max-width: 500px) {
  .modal-header {
    padding: 16px 20px;
  }
  
  .modal-body {
    padding: 20px;
  }
  
  .modal-footer {
    padding: 16px 20px;
  }
  
  .modal-info {
    grid-template-columns: 1fr;
    gap: 8px;
  }
}
</style>