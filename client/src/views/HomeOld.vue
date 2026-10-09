<script setup>
import Card from '../components/Card.vue'
import { ref, onMounted, computed, inject, onActivated, onDeactivated, defineOptions } from 'vue'
import axios from 'axios'
import { useRouter } from 'vue-router'

defineOptions({
  name: 'HomePage'
})

const selectedRegion = ref('')
const allItems = ref([])
const searchQuery=ref('')
const isDark = inject('isDark')
const items = computed(() => {
  if (searchQuery.value.length>0) return allItems.value.filter(country =>
  country.name.common.toLowerCase().slice(0, searchQuery.value.length)===searchQuery.value.toLowerCase()
  )
  return allItems.value
})
const router = useRouter()
const scrollY = ref(0)


async function fetchItems() {
  try {
    let url = 'https://restcountries.com/v3.1/all?fields=name,population,region,capital,flags,cca3';
    if (selectedRegion.value){
      url = `https://restcountries.com/v3.1/region/${selectedRegion.value}?fields=name,population,region,capital,flags,cca3`
    }
    const response = await axios.get(url, {
      params: {
        region: selectedRegion.value
      }
    })
    allItems.value = response.data
  } catch (error) {
    console.error('Ошибка при загрузке данных:', error)
  }
}

function goToCountry(key){
  router.push(`/country/${key}`)
}
// Загрузка при старте
onMounted(()=>{if(allItems.value.length===0) fetchItems()})
onDeactivated(() => {
  scrollY.value = window.scrollY
})

onActivated(() => {
  window.scrollTo(0, scrollY.value)
})

</script>

<template>
<div>
    <div class="filterContainer">
        <div class="inputWithIcon" :class="{ darkItem: isDark }">
          <i class="fa fa-search"></i>
          <input v-model="searchQuery" placeholder="Search for a country.." :class="{ darkInput: isDark }">
      </div>
      <div class="selectDiv">
        <select v-model="selectedRegion" @change="fetchItems" :class="{ darkItem: isDark }">
          <option value="">FilterByRegion</option>
          <option value="africa">Africa</option>
          <option value="america">America</option>
          <option value="asia">Asia</option>
          <option value="europe">Europe</option>
          <option value="oceania">Oceania</option>
        </select>
      </div>
    </div>
  </div>
  <div class="wrapper">
    <Card  :class="{ darkItem: isDark }" v-for="item in items" :flag="item.flags.png" :name="item.name.common" 
    :population="item.population" :region="item.region" :capital="item.capital[0]" @click="goToCountry(item.cca3)" 
    :title="item.name.common" />
  </div>
</template>

<style scoped>
  .wrapper{
    display: grid;
    max-width: 90vw;
    padding-top: 9vh;
    width: 100%;
    grid-template-columns: repeat(4, 1fr);
    gap: 5vw;
    margin: 0 auto;
    overflow-y: auto;
    flex: 1;
  }
  select{
    border: none;
    padding-left: 2vw;
    padding-right: 4vw;
    margin-right: 2vw;
    box-shadow: 0 0 1vh rgba(136, 136, 136, .2);
  }
  .darkItem{
    background: hsl(209, 23%, 22%) !important;
    color: hsl(0, 100%, 100%) !important;
    caret-color: hsl(0, 100%, 100%);
  }
  .darkInput::placeholder{
    color: hsl(0, 100%, 100%);
    opacity: 1;
    font-weight: 250;
  }
  .darkInput{
    color: hsl(0, 100%, 100%);
  }
  input {
    margin-left: 3vw;
    border: none;
    background: transparent;
  }
  input:focus{
    outline: none;
    box-shadow: none;
    border: none;
  }
  .selectDiv{
    background: hsl(0, 100%, 100%);
  }
  .inputWithIcon{
    width: 20%;
    color: grey;
    padding-left: 3vw;
    height: 5vh;
    display: flex;
    align-items: center;
    background: hsl(0, 100%, 100%);
    box-shadow: 0 0 1vh rgba(136, 136, 136, .2);
  }
  @media (max-width: 900px) and (max-aspect-ratio: 1/1) and (min-aspect-ratio: 0.6/1){
    .wrapper{
      grid-template-columns: repeat(3, 1fr);
  }
  .inputWithIcon{
    width: 30%;
  }
}
@media (max-width: 768px) and (max-aspect-ratio: 0.6/1) {
    .wrapper{
      grid-template-columns: 1fr;
      max-width: 70vw;
      gap: 5.7vh;
  }
  .selectDiv, .inputWithIcon{
    z-index: 10;
  }
  .fa-search{
    margin-right: 2vw;
  }
  input{
    width: 100%;
    margin-left: 5vw;
  }
  input, select{
    display: flex;
  }
  .inputWithIcon{
    width: 45%;
  }
  .x2{
    font-size: 100%;
  }
  .itemMenuLeft{
    margin-left: 4vw;
  }
  .itemMenuLeft{
    margin-right: 4vw;
  }
  img{
    width: 90%;
  }
}
</style>
