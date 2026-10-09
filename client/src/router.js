// router.js
import { createRouter, createWebHashHistory } from 'vue-router'
import Home from './views/Home.vue'
import DishPage from './views/DishPage.vue'
import Error404 from './views/Error404.vue'
import Constructor from './views/Constructor.vue'
import ProjectInfo from './views/ProjectInfo.vue'
import Auth from './views/Auth.vue'
import RationsList from './views/RationsList.vue'
import AdminDishForm from './views/AdminDishForm.vue'

const routes = [
  { path: '/', redirect: '/catalog' },
  { path: '/catalog', name: 'HomePage', component: Home },
  { path: '/constructor', component: Constructor },
  { path: '/dish/:id', component: DishPage },
  { path: '/about', component: ProjectInfo },
  { path: '/login', component: Auth },
  { path: '/rations', component: RationsList },
  { path: '/admin/dish/create', component: AdminDishForm },
  { path: '/admin/dish/edit/:id', component: AdminDishForm },
  { path: '/:pathMatch(.*)*', component: Error404 },  // было :patchMatch — опечатка
]

const router = createRouter({
  history: createWebHashHistory(),
  routes
})

export default router