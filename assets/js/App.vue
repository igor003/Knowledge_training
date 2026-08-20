<template>
  <div class="app-shell">
    <aside class="app-sidebar">
      <div class="app-brand">
        <span>
          <strong>Knowledge Training</strong>
          <small>Skill matrix & testing</small>
        </span>
      </div>

      <nav class="app-nav" aria-label="Main navigation">
        <button
          v-for="module in modules"
          :key="module.key"
          class="app-nav__item"
          :class="{ 'app-nav__item--active': module.key === activeModule }"
          type="button"
          @click="activeModule = module.key"
        >
          {{ module.label }}
        </button>
      </nav>
    </aside>

    <main class="app-main">
      <header class="app-header">
        <div>
          <p class="app-kicker">MVP workspace</p>
          <h1>{{ activeModuleLabel }}</h1>
        </div>

        <div class="language-switcher" aria-label="Interface language">
          <button
            v-for="language in languages"
            :key="language"
            class="language-switcher__item"
            :class="{ 'language-switcher__item--active': language === activeLanguage }"
            type="button"
            @click="activeLanguage = language"
          >
            {{ language }}
          </button>
        </div>
      </header>

      <section class="dashboard-grid" aria-label="Qualification summary">
        <ModuleCard
          v-for="metric in metrics"
          :key="metric.label"
          :label="metric.label"
          :value="metric.value"
          :tone="metric.tone"
        />
      </section>

      <section class="module-panel">
        <div class="module-panel__header">
          <div>
            <p class="app-kicker">Current module</p>
            <h2>{{ activeModuleLabel }}</h2>
          </div>
          <button class="btn btn-primary" type="button">Open module</button>
        </div>

        <div class="module-panel__body">
          <p>
            The application shell is ready for Symfony, Eloquent, Vue, Bootstrap,
            and locally bundled assets. Module screens will be implemented here
            according to the MVP documentation.
          </p>
        </div>
      </section>
    </main>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import ModuleCard from './components/ModuleCard.vue';

const activeLanguage = ref('ru');
const activeModule = ref('dashboard');

const languages = ['ru', 'ro', 'it', 'fr'];

const modules = [
  { key: 'dashboard', label: 'Dashboard' },
  { key: 'employees', label: 'Employees' },
  { key: 'requirements', label: 'Requirement matrix' },
  { key: 'questions', label: 'Questions' },
  { key: 'testing', label: 'Testing' },
  { key: 'skill-matrix', label: 'Skill Matrix' },
  { key: 'reports', label: 'Reports' },
  { key: 'permissions', label: 'Permissions' },
];

const metrics = [
  { label: 'Active employees', value: '0', tone: 'neutral' },
  { label: 'Qualified', value: '0', tone: 'success' },
  { label: 'Not qualified', value: '0', tone: 'danger' },
  { label: 'Expiring soon', value: '0', tone: 'warning' },
];

const activeModuleLabel = computed(() => {
  return modules.find((module) => module.key === activeModule.value)?.label ?? 'Dashboard';
});
</script>
