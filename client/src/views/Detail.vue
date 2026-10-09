<script setup>
import { onMounted, ref, watch, inject,computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'


const route = useRoute()
const router = useRouter()
const countryCode = ref(route.params.code)
const country = ref('')
const error = ref('')
const borders = ref([])
const isDark = inject('isDark')
const noBorders = ref('Border countries:')
const joinedNames = ref('')
const joinedCurrencies = ref('')

function insertDotsFromEnd(str) {
    const string = String(str)
    return string
      .split('')
      .reverse()
      .join('')
      .replace(/(.{3})/g, '$1.')
      .split('')
      .reverse()
      .join('')
      .replace(/^\./, ''); 
}

async function getCountry(Code) {
  try {
    const response = await fetch(`https://restcountries.com/v3.1/alpha/${Code}`);
    if (response.status===400 || response.status===404) return false
    const data = await response.json()
    return data[0]
  } catch (error) {
    console.error('Ошибка при загрузке данных:', error)
    error.value = 'Не удалось загрузить данные'
  }
}
async function fetchItem() {
  country.value = await getCountry(countryCode.value)
  if (!country.value) return 0
  const nativeNames = country.value.name.nativeName || {}
  const commonNames = Array.from(new Set(Object.values(nativeNames).map(n => n.common)))
  joinedNames.value = commonNames.join(", ")
  const currencies = country.value.currencies || {}
  const currencyNames= Object.values(Object.values(currencies)).map(n=>n.name)
  joinedCurrencies.value = currencyNames.join(",")
  if (country.value.borders) {
    const borderPromises = country.value.borders.map(border => getCountry(border))
    borders.value = await Promise.all(borderPromises)
  } else {
    borders.value = []  // если нет borders
    noBorders.value = 'This country has no border countries'
  }
}

function goBack(){
  router.back()
}

// Загрузка при старте
onMounted(async () => {
  await fetchItem()
})

watch(() => route.params.code, async (newCode) => {
  countryCode.value = newCode
  borders.value = []
  await fetchItem()
})
</script>

<template>
<div style="height: 100%; display: flex; flex-direction: column; align-items: center;">
    <a :class="{ darkItem: isDark }" @click="goBack" class="back"><i class="fa fa-arrow-left" aria-hidden="true"></i> &nbsp;&nbsp; Back </a>
    <p v-if="error">{{ error }}</p>
    <div class="countryContainer" v-if="country">
      <img :src="'https://plg-two.vercel.app/api/proxy?url=' + country.flags.png">
      <div class="details">
        <h2> {{ country.name.common }} </h2>
        <div class="params">
          <div class="paramsBlock">
            <p><b>Native name<span v-if="joinedNames.includes(',')">s</span>: </b>{{ joinedNames }}</p>
            <p><b>Population</b> {{ insertDotsFromEnd(country.population) }}</p>
            <p><b>Sub region:</b> {{ country.subregion?country.subregion:"-" }}</p>
            <p><b>Capital:</b> {{ country.capital?country.capital.join(", "):"-" }}</p>
          </div>
          <div class="paramsBlock">
            <p><b>Top Level Domain:</b> {{ country.tld?country.tld.join(", "):"-" }}</p>
            <p><b>Currencies:</b> {{ joinedCurrencies }}</p>
            <p><b>Languages:</b> {{ Object.values(country.languages).join(', ') }}</p>
          </div>
          </div>
          <b class="borderTitle">{{ noBorders }}&nbsp;&nbsp;</b>
          <div class="border" v-for="border in borders">
            <router-link :key="border.cca3" :to="`/country/${border.cca3}`" :class="{ darkItem: isDark }"
            :title="border.name.common">{{ border.name.common }}</router-link>
            <span>&nbsp;</span>
        </div>
        </div>
    </div>
    <div class="countryContainer unknownCountry" v-else-if="country===false">
      <img  style="min-width: 20% !important; max-height: 100% !important;" src="../assets/unknown.png">
      <div>
        <h2>This country is not found</h2>
        <p>You may have made a mistake in the link address</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
body{
  overflow: hidden;
}
.back{
  margin-top: 17vh;
  margin-left: 5vw;
  align-self: flex-start;
}
.params{
  display: flex;
  justify-content: space-between;
  gap: 4vw;
}
h2{
  font-weight: 800;
}
.paramsBlock{
  width: 50%;
}
a{
  text-decoration: none;
  display: inline-block;
  margin: 2vw 1vw 0 0;
  padding: 0.5vh 0.2vw;
  text-align: center;
  min-width: 10vw;
  box-shadow: 0 0 1vh rgba(136, 136, 136, .2);
  background: hsl(0, 100%, 100%);
  color: hsl(200, 15%, 8%);
  border-radius: 3px;
}
a:hover:not(.darkItem) {
    background-color: rgba(136, 136, 136, .2) !important;
  }
  .border{
    display: inline-block;
  }
  .right{
    display: flex;
  }
  .wrapper{
    display: grid;
    grid-template-columns: repeat(4, 1fr);
  }
  .details{
    width: 50%;
    margin-bottom: 2vh;
  }
  .darkItem{
    background: hsl(209, 23%, 22%);
    color: hsl(0, 100%, 100%);
  }
  .countryContainer{
    display: flex;
    justify-content: space-between;
    align-items: center;
    height: 100%;
    width: 90vw;
    margin-left: 5vw;
    margin-right: 5vw;
  }
  .unknownCountry{
    justify-content: space-evenly;
  }
  img{
    width: 45%;
    min-height: 56%;
    max-height: 70%;
  }
  body{
    overflow: hidden;
  }
  @media (max-width: 900px) and (min-width: 768px),(max-aspect-ratio: 1/1)and (min-aspect-ratio: 0.6/1){
    img{
      width: auto;
      min-height: 40vw;
      max-height: 100%;
    }
    .back{
      margin-top: 14vh;
      margin-bottom: 6vh;
    }
    .unknownCountry{
      align-items: stretch;
    }
    .countryContainer {
      justify-content: space-around;
      width: 95vw;
      flex-direction: column;
    }
    .unknownCountry{
      justify-content: flex-start;
    }
    .details{
      width: 90%;
    }
    .params{
      width: 100%;
    }
  }
  @media  (max-width: 900px) and (min-width: 768px),((max-aspect-ratio: 1/1)and (min-aspect-ratio: 0.6/1) 
  and (min-width: 768px)){
    .countryContainer{
      font-size: 150% !important;
    }
  }
  @media (max-width: 768px)and (max-aspect-ratio: 0.6/1){
    body{
      overflow-y: scroll;
    }
  .countryContainer,.params {
    flex-direction: column;
  }
  .details{
    margin-right: auto;
    width: 100%;
  }
  .params{
    gap: 2vh;
  }
  .paramsBlock{
    width: 100%;
  }
  .borderTitle{
    display: block;
    font-size: x-large;
    margin-top: 2vh;
    margin-bottom: 2vh;
  }
  a{
    min-width: 26vw;
    height: 3.5vh;
    line-height: 3.5vh;
    margin: 1vw 1vw 1vw 0;
  }
  .details::after{
    content: '';
    display: block;
    height: 5vh;
  }
  img{
    min-height: auto;
    width: 90vw;
    height: auto;
    margin-bottom: 3vh;
  }
  .back{
    margin-top: 15vh;
    margin-bottom: 7vh;
  }
}
</style>
