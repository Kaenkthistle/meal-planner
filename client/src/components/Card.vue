<template>
  <div class="card" :class="{ 'dark-mode': isDarkMode }">
    <img 
      ref="refImg" 
      :src="imageSrc" 
      :class="{ tall: tallImg }" 
      @error="useFallback"
      loading="lazy"
      decoding="async"
    >
    <div class="cardContent">
      <h3 class="card-title">{{ name }}</h3>
      <div class="nutrition-left">
        <p class="nutrition-item">{{ proteins }} б.</p>
        <p class="nutrition-item">{{ fats }} ж.</p>
        <p class="nutrition-item">{{ carbs }} у.</p>
      </div>
      <div class="card-footer">
        <p class="footer-item">{{ mass }} г.</p>
        <p class="footer-item">{{ calories }} ккал</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { STORAGE_BASE_URL } from '@/config/api'

const { id, name, proteins, fats, carbs, mass, calories } = defineProps([
  'id',
  'name',
  'proteins',
  'fats',
  'carbs',
  'mass',
  'calories'
])

const refImg = ref(null)
const tallImg = ref(false)
const isDarkMode = ref(false)

const imageSrc = ref(`${STORAGE_BASE_URL}/images/unknown_dish.webp`)

const useFallback = () => {
  imageSrc.value = `${STORAGE_BASE_URL}/images/unknown_dish.webp`
}

const tryLoadRealImage = () => {
  const img = new Image()
  const originalUrl = `${STORAGE_BASE_URL}/images/gallery/${id}.webp`

  img.onload = () => {
    imageSrc.value = originalUrl
  }

  img.onerror = () => {
  }

  img.src = originalUrl
}

const checkDarkMode = () => {
  isDarkMode.value =
    document.documentElement.classList.contains('dark') ||
    document.documentElement.classList.contains('dark-theme') ||
    document.body.classList.contains('dark') ||
    document.body.classList.contains('dark-theme') ||
    (window.matchMedia &&
      window.matchMedia('(prefers-color-scheme: dark)').matches)
}

onMounted(() => {
  checkDarkMode()
  tryLoadRealImage()

  const observer = new MutationObserver(checkDarkMode)

  observer.observe(document.documentElement, { attributes: true })
  observer.observe(document.body, { attributes: true })

  window
    .matchMedia('(prefers-color-scheme: dark)')
    .addEventListener('change', checkDarkMode)
})
</script>

<style scoped>
img {
  height: 54%;
  width: 100%;
  object-fit: cover;
}

.tall {
  object-fit: contain !important;
}

.card {
  background: #ffffff;
  box-shadow: 0 0 1vh rgba(136, 136, 136, .2);
  aspect-ratio: 1/1.3;
  border-radius: 3px;
  overflow: hidden;
  cursor: pointer;
  container-type: inline-size;
  display: flex;
  flex-direction: column;
  transition: all 0.2s ease;
}

.cardContent {
  padding: 7%;
  display: flex;
  flex-direction: column;
  flex: 1;
  height: 46%;
}

.card-title {
  margin: 0 0 5% 0 !important;
  font-size: 6.5cqi !important;
  line-height: 1.3;
  font-weight: 600;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  min-height: 2.6em;
  color: #1a1a1a;
}

.nutrition-left {
  display: flex;
  justify-content: flex-start;
  gap: 8cqi;
  margin: auto 0;
  padding-left: 0;
}

.nutrition-item {
  margin: 0 !important;
  font-size: 5cqi !important;
  color: #555555;
  font-weight: 500;
}

.card-footer {
  display: flex;
  justify-content: space-between;
  margin-top: auto;
  padding-top: 5%;
}

.footer-item {
  margin: 0 !important;
  font-size: 5.2cqi !important;
  color: #555555;
  font-weight: 500;
}

.card:hover {
  background-color: rgba(136, 136, 136, .1) !important;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.card.dark-mode,
:global(.dark) .card,
:global(.dark-theme) .card,
:global([data-theme="dark"]) .card {
  background: hsl(207, 26%, 22%) !important;
  border: 1px solid rgba(255, 255, 255, 0.08);
}

.card.dark-mode:hover,
:global(.dark) .card:hover,
:global(.dark-theme) .card:hover,
:global([data-theme="dark"]) .card:hover {
  background: hsl(207, 26%, 28%) !important;
  transform: translateY(-2px);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
}

.card.dark-mode .card-title,
:global(.dark) .card .card-title,
:global(.dark-theme) .card .card-title,
:global([data-theme="dark"]) .card .card-title {
  color: #ffffff !important;
}

.card.dark-mode .nutrition-item,
.card.dark-mode .footer-item,
:global(.dark) .card .nutrition-item,
:global(.dark) .card .footer-item,
:global(.dark-theme) .card .nutrition-item,
:global(.dark-theme) .card .footer-item,
:global([data-theme="dark"]) .card .nutrition-item,
:global([data-theme="dark"]) .card .footer-item {
  color: #e0e0e0 !important;
}

.card.dark-mode:hover .card-title,
.card.dark-mode:hover .nutrition-item,
.card.dark-mode:hover .footer-item,
:global(.dark) .card:hover .card-title,
:global(.dark) .card:hover .nutrition-item,
:global(.dark) .card:hover .footer-item {
  color: #ffffff !important;
}
</style>