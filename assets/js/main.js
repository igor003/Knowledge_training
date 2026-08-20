import $ from 'jquery';
import { Alert, Modal } from 'bootstrap';
import 'bootstrap/dist/css/bootstrap.min.css';
import DataTable from 'datatables.net-bs5';
import 'datatables.net-bs5/css/dataTables.bootstrap5.min.css';
import 'datatables.net-rowreorder-bs5';
import 'datatables.net-rowreorder-bs5/css/rowReorder.bootstrap5.min.css';
import { createApp } from 'vue';
import App from './App.vue';
import '../styles/app.scss';

window.$ = $;
window.jQuery = $;

if (typeof $.unique !== 'function' && typeof $.uniqueSort === 'function') {
  $.unique = $.uniqueSort;
}

const dataTableTranslations = {
  ru: {
    search: 'Поиск по всем полям:',
    columnFilter: 'Фильтр',
    lengthMenu: 'Показать _MENU_ строк',
    info: 'Показано _START_-_END_ из _TOTAL_',
    infoEmpty: 'Нет строк',
    zeroRecords: 'Ничего не найдено',
    paginate: {
      first: 'Первая',
      last: 'Последняя',
      next: 'След.',
      previous: 'Пред.',
    },
  },
  ro: {
    search: 'Cautare in toate campurile:',
    columnFilter: 'Filtru',
    lengthMenu: 'Arata _MENU_ randuri',
    info: 'Afisat _START_-_END_ din _TOTAL_',
    infoEmpty: 'Nu exista randuri',
    zeroRecords: 'Nu au fost gasite rezultate',
    paginate: {
      first: 'Prima',
      last: 'Ultima',
      next: 'Urm.',
      previous: 'Prec.',
    },
  },
  it: {
    search: 'Cerca in tutti i campi:',
    columnFilter: 'Filtro',
    lengthMenu: 'Mostra _MENU_ righe',
    info: 'Mostrate _START_-_END_ di _TOTAL_',
    infoEmpty: 'Nessuna riga',
    zeroRecords: 'Nessun risultato trovato',
    paginate: {
      first: 'Prima',
      last: 'Ultima',
      next: 'Succ.',
      previous: 'Prec.',
    },
  },
  fr: {
    search: 'Recherche tous les champs :',
    columnFilter: 'Filtre',
    lengthMenu: 'Afficher _MENU_ lignes',
    info: 'Affichage _START_-_END_ sur _TOTAL_',
    infoEmpty: 'Aucune ligne',
    zeroRecords: 'Aucun resultat trouve',
    paginate: {
      first: 'Premiere',
      last: 'Derniere',
      next: 'Suiv.',
      previous: 'Prec.',
    },
  },
};

const dataTables = new WeakMap();
const svgNamespace = 'http://www.w3.org/2000/svg';
const sidebarStorageKey = 'knowledge_training_admin_sidebar_collapsed';

const createSvgIcon = (viewBox, paths) => {
  const icon = document.createElementNS(svgNamespace, 'svg');
  icon.setAttribute('viewBox', viewBox);
  icon.setAttribute('aria-hidden', 'true');
  icon.setAttribute('focusable', 'false');

  paths.forEach((pathDefinition) => {
    const path = document.createElementNS(svgNamespace, 'path');

    Object.entries(pathDefinition).forEach(([attribute, value]) => {
      path.setAttribute(attribute, value);
    });

    icon.appendChild(path);
  });

  return icon;
};

const decorateDataTableSearch = (table) => {
  const container = table.closest('.dt-container');

  if (!container) {
    return;
  }

  const searchInput = container.querySelector('.dt-search input');

  if (!searchInput || searchInput.closest('.admin-search-field')) {
    return;
  }

  const searchField = document.createElement('span');
  searchField.className = 'admin-search-field';

  const searchIcon = document.createElementNS(svgNamespace, 'svg');
  searchIcon.setAttribute('class', 'admin-search-field__icon');
  searchIcon.setAttribute('viewBox', '0 0 16 16');
  searchIcon.setAttribute('aria-hidden', 'true');
  searchIcon.setAttribute('focusable', 'false');

  const searchCircle = document.createElementNS(svgNamespace, 'circle');
  searchCircle.setAttribute('cx', '7');
  searchCircle.setAttribute('cy', '7');
  searchCircle.setAttribute('r', '4.6');

  const searchHandle = document.createElementNS(svgNamespace, 'path');
  searchHandle.setAttribute('d', 'm10.4 10.4 3.1 3.1');

  searchIcon.append(searchCircle, searchHandle);
  searchInput.parentNode.insertBefore(searchField, searchInput);
  searchField.appendChild(searchIcon);
  searchField.appendChild(searchInput);
};

const createCatalogMultiselect = (select) => {
  if (select.dataset.multiselectReady === 'true') {
    return;
  }

  select.dataset.multiselectReady = 'true';

  const isRequired = select.required;
  const placeholder = select.dataset.placeholder || 'Select';
  const searchPlaceholder = select.dataset.searchPlaceholder || 'Search';
  const selectedTemplate = select.dataset.selectedTemplate || '{count} selected';
  const summaryMode = select.dataset.summaryMode || 'labels';
  const emptyText = select.dataset.emptyText || 'No options';
  const isOrdered = select.dataset.ordered === 'true';
  const wrapper = document.createElement('div');
  const button = document.createElement('button');
  const buttonText = document.createElement('span');
  const buttonIcon = createSvgIcon('0 0 16 16', [
    {
      d: 'M4 6l4 4 4-4',
      fill: 'none',
      stroke: 'currentColor',
      'stroke-width': '1.8',
      'stroke-linecap': 'round',
      'stroke-linejoin': 'round',
    },
  ]);
  const panel = document.createElement('div');
  const selectedList = document.createElement('div');
  const search = document.createElement('input');
  const list = document.createElement('div');
  const empty = document.createElement('div');
  let draggedValue = '';

  wrapper.className = 'admin-multiselect';
  button.className = 'admin-multiselect__button';
  button.type = 'button';
  button.setAttribute('aria-haspopup', 'listbox');
  button.setAttribute('aria-expanded', 'false');
  buttonIcon.classList.add('admin-multiselect__chevron');
  panel.className = 'admin-multiselect__panel';
  selectedList.className = 'admin-multiselect__selected-list';
  search.className = 'admin-multiselect__search';
  search.type = 'search';
  search.placeholder = searchPlaceholder;
  list.className = 'admin-multiselect__list';
  empty.className = 'admin-multiselect__empty';
  empty.textContent = emptyText;

  select.required = false;
  select.classList.add('admin-catalog-multiselect--native');
  select.parentNode.insertBefore(wrapper, select.nextSibling);

  button.append(buttonText, buttonIcon);
  if (isOrdered) {
    panel.append(selectedList);
  }
  panel.append(search, list, empty);
  wrapper.append(button, panel);

  const items = Array.from(select.options).map((option) => {
    const optionLabel = document.createElement('label');
    const checkbox = document.createElement('input');
    const text = document.createElement('span');

    optionLabel.className = 'admin-multiselect__option';
    checkbox.type = 'checkbox';
    checkbox.value = option.value;
    checkbox.checked = option.selected;
    text.textContent = option.textContent.trim();

    optionLabel.append(checkbox, text);
    list.appendChild(optionLabel);

    checkbox.addEventListener('change', () => {
      option.selected = checkbox.checked;
      if (isOrdered) {
        const selectedValues = orderedSelectedValues().filter((value) => value !== option.value);
        if (checkbox.checked) {
          selectedValues.push(option.value);
        }
        setSelectedOrder(selectedValues);
      }
      select.dispatchEvent(new Event('change', { bubbles: true }));
      updateSummary();
      wrapper.classList.remove('admin-multiselect--invalid');
    });

    return {
      checkbox,
      label: text.textContent.toLowerCase(),
      option,
      optionLabel,
    };
  });

  const close = () => {
    wrapper.classList.remove('admin-multiselect--open');
    button.setAttribute('aria-expanded', 'false');
  };

  const open = () => {
    wrapper.classList.add('admin-multiselect--open');
    button.setAttribute('aria-expanded', 'true');
    search.focus();
  };

  const itemByValue = (value) => items.find((item) => item.option.value === value);

  const orderedSelectedValues = () => Array.from(select.options)
    .filter((option) => option.selected)
    .map((option) => option.value);

  const setSelectedOrder = (selectedValues) => {
    if (!isOrdered) {
      return;
    }

    const selectedSet = new Set(selectedValues);
    const orderedOptions = [
      ...selectedValues.map((value) => itemByValue(value)?.option).filter(Boolean),
      ...items.map((item) => item.option).filter((option) => !selectedSet.has(option.value)),
    ];
    const orderMap = new Map(orderedOptions.map((option, index) => [option.value, index]));

    orderedOptions.forEach((option) => select.appendChild(option));
    items.sort((first, second) => (orderMap.get(first.option.value) ?? 0) - (orderMap.get(second.option.value) ?? 0));
    renderSelectedList();
  };

  const reorderSelectedValue = (sourceValue, targetValue) => {
    if (!sourceValue || !targetValue || sourceValue === targetValue) {
      return;
    }

    const values = orderedSelectedValues();
    const sourceIndex = values.indexOf(sourceValue);
    const targetIndex = values.indexOf(targetValue);

    if (sourceIndex === -1 || targetIndex === -1) {
      return;
    }

    values.splice(sourceIndex, 1);
    values.splice(targetIndex, 0, sourceValue);
    setSelectedOrder(values);
    select.dispatchEvent(new Event('change', { bubbles: true }));
    updateSummary();
  };

  function renderSelectedList() {
    if (!isOrdered) {
      return;
    }

    selectedList.replaceChildren();
    const selected = items.filter((item) => item.option.selected);
    selectedList.hidden = selected.length === 0;

    selected.forEach((item) => {
      const selectedItem = document.createElement('div');
      const handle = createSvgIcon('0 0 16 16', [
        {
          d: 'M6 3.5h.01M10 3.5h.01M6 8h.01M10 8h.01M6 12.5h.01M10 12.5h.01',
          fill: 'none',
          stroke: 'currentColor',
          'stroke-width': '2.2',
          'stroke-linecap': 'round',
        },
      ]);
      const label = document.createElement('span');

      selectedItem.className = 'admin-multiselect__selected-item';
      selectedItem.draggable = true;
      selectedItem.dataset.value = item.option.value;
      handle.classList.add('admin-multiselect__selected-handle');
      label.textContent = item.option.textContent.trim();
      selectedItem.append(handle, label);
      selectedList.appendChild(selectedItem);

      selectedItem.addEventListener('dragstart', (event) => {
        draggedValue = item.option.value;
        selectedItem.classList.add('admin-multiselect__selected-item--dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', draggedValue);
      });
      selectedItem.addEventListener('dragend', () => {
        draggedValue = '';
        selectedItem.classList.remove('admin-multiselect__selected-item--dragging');
      });
      selectedItem.addEventListener('dragover', (event) => {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
      });
      selectedItem.addEventListener('drop', (event) => {
        event.preventDefault();
        reorderSelectedValue(draggedValue || event.dataTransfer.getData('text/plain'), item.option.value);
      });
    });
  }

  function updateSummary() {
    const selected = items.filter((item) => item.option.selected);
    const selectedLabels = selected.map((item) => item.option.textContent.trim());

    renderSelectedList();

    if (selected.length === 0) {
      buttonText.textContent = placeholder;
      wrapper.classList.add('admin-multiselect--empty');
      return;
    }

    wrapper.classList.remove('admin-multiselect--empty');

    if (summaryMode !== 'count' && selectedLabels.length <= 2) {
      buttonText.textContent = selectedLabels.join(', ');
      return;
    }

    buttonText.textContent = selectedTemplate.replace('{count}', String(selected.length));
  }

  const filterOptions = () => {
    const query = search.value.trim().toLowerCase();
    let visibleCount = 0;

    items.forEach((item) => {
      const isVisible = query === '' || item.label.includes(query);
      item.optionLabel.hidden = !isVisible;

      if (isVisible) {
        visibleCount += 1;
      }
    });

    empty.hidden = visibleCount > 0;
  };

  button.addEventListener('click', () => {
    if (wrapper.classList.contains('admin-multiselect--open')) {
      close();
      return;
    }

    open();
  });

  search.addEventListener('input', filterOptions);

  wrapper.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      close();
      button.focus();
    }
  });

  select.form?.addEventListener('submit', (event) => {
    if (!isRequired || select.selectedOptions.length > 0) {
      return;
    }

    event.preventDefault();
    wrapper.classList.add('admin-multiselect--invalid');
    open();
  });

  document.addEventListener('click', (event) => {
    if (!wrapper.contains(event.target)) {
      close();
    }
  });

  updateSummary();
  filterOptions();
};

const initAdminSidebarToggle = () => {
  const shell = document.querySelector('[data-admin-shell]');
  const toggle = document.querySelector('[data-admin-sidebar-toggle]');
  const navGroups = Array.from(document.querySelectorAll('[data-admin-nav-group]'));

  if (!shell || !toggle) {
    return;
  }

  let animationTimeout;

  const applyState = (isCollapsed) => {
    if (isCollapsed) {
      navGroups.forEach((group) => {
        group.open = true;
      });
    } else {
      navGroups.forEach((group) => {
        group.open = group.classList.contains('admin-nav__group--active')
          || group.dataset.adminNavDefaultOpen === 'true';
      });
    }

    shell.classList.toggle('admin-shell--sidebar-collapsed', isCollapsed);
    document.documentElement.classList.toggle('admin-sidebar-prefers-collapsed', isCollapsed);
    toggle.setAttribute('aria-expanded', String(!isCollapsed));
  };

  applyState(window.localStorage.getItem(sidebarStorageKey) === '1');

  navGroups.forEach((group) => {
    group.addEventListener('toggle', () => {
      if (!group.open || shell.classList.contains('admin-shell--sidebar-collapsed')) {
        return;
      }

      navGroups.forEach((otherGroup) => {
        if (otherGroup !== group) {
          otherGroup.open = false;
        }
      });
    });
  });

  toggle.addEventListener('click', () => {
    const isCollapsed = !shell.classList.contains('admin-shell--sidebar-collapsed');

    shell.classList.add('admin-shell--sidebar-animating');
    applyState(isCollapsed);
    window.localStorage.setItem(sidebarStorageKey, isCollapsed ? '1' : '0');

    window.clearTimeout(animationTimeout);
    animationTimeout = window.setTimeout(() => {
      shell.classList.remove('admin-shell--sidebar-animating');
    }, 320);
  });
};

const initAutoSubmitForms = () => {
  document.querySelectorAll('[data-auto-submit-form]').forEach((form) => {
    form.querySelectorAll('[data-auto-submit-control]').forEach((control) => {
      control.addEventListener('change', () => {
        if (typeof form.requestSubmit === 'function') {
          form.requestSubmit();
          return;
        }

        form.submit();
      });
    });
  });
};

const initCompetencyMatrixLiveSearch = () => {
  document.querySelectorAll('[data-competency-matrix-live-search]').forEach((control) => {
    const form = control.form;

    if (!form) {
      return;
    }

    let timeoutId;
    control.addEventListener('input', () => {
      window.clearTimeout(timeoutId);
      timeoutId = window.setTimeout(() => {
        if (typeof form.requestSubmit === 'function') {
          form.requestSubmit();
          return;
        }

        form.submit();
      }, 300);
    });
  });
};

const initTrainingPlanModals = () => {
  document.querySelectorAll('[data-training-plan-modal]').forEach((modal) => {
    const departmentSelect = modal.querySelector('[data-training-plan-department-select]');
    const panels = Array.from(modal.querySelectorAll('[data-training-plan-department-panel]'));

    if (!departmentSelect || panels.length === 0) {
      return;
    }

    const syncCoursePanels = (departmentPanel, shouldResetCourse = false) => {
      const courseSelect = departmentPanel.querySelector('[data-training-plan-course-select]');
      const coursePanels = Array.from(departmentPanel.querySelectorAll('[data-training-plan-course-panel]'));

      if (!courseSelect) {
        return;
      }

      if (shouldResetCourse) {
        courseSelect.value = '';
      }

      coursePanels.forEach((coursePanel) => {
        const isActive = coursePanel.dataset.trainingPlanCoursePanel === courseSelect.value;

        coursePanel.hidden = !isActive;
        coursePanel.querySelectorAll('input, select, textarea, button').forEach((control) => {
          control.disabled = !isActive;
        });
      });
    };

    const syncDepartmentPanels = (shouldResetCourse = false) => {
      panels.forEach((panel) => {
        const isActive = panel.dataset.trainingPlanDepartmentPanel === departmentSelect.value;
        const courseSelect = panel.querySelector('[data-training-plan-course-select]');

        panel.hidden = !isActive;
        if (courseSelect) {
          courseSelect.disabled = !isActive;
          courseSelect.required = isActive;
        }

        if (isActive) {
          syncCoursePanels(panel, shouldResetCourse);
          return;
        }

        syncCoursePanels(panel, true);
      });
    };

    panels.forEach((panel) => {
      const courseSelect = panel.querySelector('[data-training-plan-course-select]');

      if (courseSelect) {
        courseSelect.addEventListener('change', () => syncCoursePanels(panel));
      }
    });

    departmentSelect.addEventListener('change', () => syncDepartmentPanels(true));
    syncDepartmentPanels();
  });
};

const initEmployeePeriodForms = () => {
  document.querySelectorAll('[data-employee-period-form]').forEach((form) => {
    const employeeSearch = form.querySelector('[data-employee-search]');
    const employeeSelect = form.querySelector('select[name="employee_id"]');

    if (employeeSearch && employeeSelect) {
      const employeeOptions = Array.from(employeeSelect.options)
        .filter((option) => option.value !== '')
        .map((option) => ({
        option,
        label: option.textContent.trim().toLowerCase(),
        }));
      const wrapper = document.createElement('div');
      const suggestions = document.createElement('div');
      wrapper.className = 'admin-smart-search';
      suggestions.className = 'admin-smart-search__suggestions';
      suggestions.hidden = true;
      employeeSearch.parentNode.insertBefore(wrapper, employeeSearch);
      wrapper.append(employeeSearch, suggestions);
      employeeSelect.required = false;
      employeeSelect.classList.add('admin-smart-search__source');

      const renderSuggestions = () => {
        const query = employeeSearch.value.trim().toLowerCase();
        const matches = employeeOptions
          .filter(({ label }) => query === '' || label.includes(query))
          .slice(0, 12);
        suggestions.replaceChildren();
        matches.forEach(({ option }) => {
          const suggestion = document.createElement('button');
          suggestion.type = 'button';
          suggestion.className = 'admin-smart-search__suggestion';
          suggestion.textContent = option.textContent.trim();
          suggestion.addEventListener('mousedown', (event) => event.preventDefault());
          suggestion.addEventListener('click', () => {
            employeeSelect.value = option.value;
            employeeSearch.value = option.textContent.trim();
            suggestions.hidden = true;
            employeeSelect.dispatchEvent(new Event('change', { bubbles: true }));
          });
          suggestions.appendChild(suggestion);
        });
        suggestions.hidden = matches.length === 0;
      };

      const selectedOption = employeeSelect.selectedOptions[0];
      if (selectedOption?.value) {
        employeeSearch.value = selectedOption.textContent.trim();
      }
      employeeSearch.addEventListener('focus', renderSuggestions);
      employeeSearch.addEventListener('input', () => {
        employeeSelect.value = '';
        renderSuggestions();
      });
      employeeSearch.form?.addEventListener('submit', (event) => {
        if (employeeSelect.value) {
          return;
        }
        event.preventDefault();
        employeeSearch.focus();
        renderSuggestions();
      });
      document.addEventListener('click', (event) => {
        if (!wrapper.contains(event.target)) {
          suggestions.hidden = true;
        }
      });
    }

    const typeSelect = form.querySelector('[data-period-type]');
    const dateToContainer = form.querySelector('[data-period-date-to]');
    const dateToInput = form.querySelector('input[name="date_to"]');

    if (!dateToContainer || !dateToInput) {
      return;
    }

    const syncDateTo = () => {
      const isVacation = typeSelect ? typeSelect.value === 'vacation' : form.dataset.periodType === 'vacation';
      dateToContainer.hidden = !isVacation;
      dateToInput.disabled = !isVacation;
      dateToInput.required = false;
      if (!isVacation) {
        dateToInput.value = '';
      }
    };

    typeSelect?.addEventListener('change', syncDateTo);
    syncDateTo();
  });
};

const initEmployeeAssignmentForms = () => {
  document.querySelectorAll('[data-employee-assignment-form]').forEach((form) => {
    const departmentSelect = form.querySelector('[name="factory_department_id"]');
    const sectionSelect = form.querySelector('[data-employee-section-select]');
    const functionSelect = form.querySelector('[data-employee-function-select]');

    if (!departmentSelect || !sectionSelect || !functionSelect) {
      return;
    }

    const sectionOptions = [...sectionSelect.options].filter((option) => option.value !== '');
    const functionOptions = [...functionSelect.options].filter((option) => option.value !== '');
    const functionPlaceholder = functionSelect.querySelector('option[value=""]');

    const matchesSection = (option) => option.dataset.factoryDepartmentId === departmentSelect.value;
    const matchesFunction = (option) => {
      const sectionIds = (option.dataset.sectionIds || '').split(',').filter(Boolean);

      return sectionSelect.value !== '' && sectionIds.includes(sectionSelect.value);
    };
    const functionSortOrder = (option) => {
      try {
        const orders = JSON.parse(option.dataset.sectionOrders || '{}');
        const order = Number(orders[sectionSelect.value]);

        return Number.isFinite(order) && order > 0 ? order : 999999;
      } catch (error) {
        return 999999;
      }
    };
    const sortFunctionOptions = () => {
      if (functionPlaceholder) {
        functionSelect.appendChild(functionPlaceholder);
      }

      functionOptions
        .slice()
        .sort((first, second) => {
          const firstVisible = matchesFunction(first);
          const secondVisible = matchesFunction(second);

          if (firstVisible !== secondVisible) {
            return firstVisible ? -1 : 1;
          }

          if (firstVisible && secondVisible) {
            const orderDiff = functionSortOrder(first) - functionSortOrder(second);
            if (orderDiff !== 0) {
              return orderDiff;
            }
          }

          return first.textContent.trim().localeCompare(second.textContent.trim());
        })
        .forEach((option) => functionSelect.appendChild(option));
    };

    const applyFilters = (resetInvalidSelection = true) => {
      sectionOptions.forEach((option) => {
        const visible = matchesSection(option);
        option.hidden = !visible;
        option.disabled = !visible;
      });

      if (resetInvalidSelection && sectionSelect.value !== '' && !matchesSection(sectionSelect.selectedOptions[0])) {
        sectionSelect.value = '';
      }

      functionOptions.forEach((option) => {
        const visible = matchesFunction(option);
        option.hidden = !visible;
        option.disabled = !visible;
      });
      sortFunctionOptions();

      if (resetInvalidSelection && functionSelect.value !== '' && !matchesFunction(functionSelect.selectedOptions[0])) {
        functionSelect.value = '';
      }
    };

    departmentSelect.addEventListener('change', () => {
      sectionSelect.value = '';
      functionSelect.value = '';
      applyFilters();
    });

    sectionSelect.addEventListener('change', () => {
      functionSelect.value = '';
      applyFilters();
    });

    applyFilters(false);
  });
};

const initCompetencyForms = () => {
  document.querySelectorAll('[data-competency-pair-picker]').forEach((picker) => {
    const sectionSearch = picker.querySelector('[data-competency-section-search]');
    const sectionSuggestions = picker.querySelector('[data-competency-section-suggestions]');
    const functionSearch = picker.querySelector('[data-competency-function-search]');
    const functionSuggestions = picker.querySelector('[data-competency-function-suggestions]');
    const addButton = picker.querySelector('[data-competency-add-function]');
    const list = picker.querySelector('[data-competency-function-list]');

    if (!sectionSearch || !sectionSuggestions || !functionSearch || !functionSuggestions || !addButton || !list) {
      return;
    }

    let selectedSectionId = '';
    let selectedPair = '';

    const hide = (element) => {
      element.hidden = true;
    };

    const selectedPairs = () => new Set(
      Array.from(list.querySelectorAll('input[name="function_pairs[]"]')).map((input) => input.value),
    );

    const availableFunction = (suggestion) => suggestion.dataset.sectionId === selectedSectionId
      && !selectedPairs().has(suggestion.dataset.pair);

    const filter = (input, suggestions, predicate) => {
      if (input.disabled) {
        suggestions.hidden = true;
        return;
      }
      const query = input.value.trim().toLocaleLowerCase();
      let visibleCount = 0;
      suggestions.querySelectorAll('.admin-smart-search__suggestion').forEach((suggestion) => {
        const visible = predicate(suggestion) && (query === '' || suggestion.textContent.toLocaleLowerCase().includes(query));
        suggestion.hidden = !visible;
        if (visible) {
          visibleCount += 1;
        }
      });
      suggestions.hidden = visibleCount === 0;
    };

    sectionSearch.addEventListener('input', () => {
      selectedSectionId = '';
      selectedPair = '';
      functionSearch.value = '';
      functionSearch.disabled = true;
      hide(functionSuggestions);
      filter(sectionSearch, sectionSuggestions, () => true);
    });
    sectionSearch.addEventListener('focus', () => filter(sectionSearch, sectionSuggestions, () => true));
    sectionSuggestions.addEventListener('click', (event) => {
      const suggestion = event.target.closest('[data-section-id]');
      if (!suggestion) {
        return;
      }
      selectedSectionId = suggestion.dataset.sectionId || '';
      selectedPair = '';
      sectionSearch.value = suggestion.textContent.trim();
      functionSearch.value = '';
      functionSearch.disabled = selectedSectionId === '';
      hide(sectionSuggestions);
    });

    functionSearch.addEventListener('input', () => {
      selectedPair = '';
      filter(functionSearch, functionSuggestions, availableFunction);
    });
    functionSearch.addEventListener('focus', () => filter(functionSearch, functionSuggestions, availableFunction));
    functionSuggestions.addEventListener('click', (event) => {
      const suggestion = event.target.closest('[data-pair]');
      if (!suggestion) {
        return;
      }
      selectedPair = suggestion.dataset.pair || '';
      functionSearch.value = suggestion.textContent.trim();
      hide(functionSuggestions);
    });
    document.addEventListener('click', (event) => {
      if (!picker.contains(event.target)) {
        hide(sectionSuggestions);
        hide(functionSuggestions);
      }
    });

    addButton.addEventListener('click', () => {
      const pair = selectedPair;
      const selectedFunction = functionSuggestions.querySelector(`[data-pair="${CSS.escape(pair)}"]`);

      if (!pair || !selectedFunction || selectedPairs().has(pair)) {
        return;
      }

      const item = document.createElement('div');
      const label = document.createElement('span');
      const criticalLabel = document.createElement('label');
      const criticalInput = document.createElement('input');
      const criticalText = document.createElement('span');
      const remove = document.createElement('button');
      const hidden = document.createElement('input');
      item.className = 'competency-pair-picker__item';
      item.dataset.pair = pair;
      label.textContent = `${sectionSearch.value} / ${selectedFunction.textContent.trim()}`;
      criticalLabel.className = 'competency-pair-picker__critical';
      criticalInput.type = 'checkbox';
      criticalInput.name = `function_pair_critical[${pair}]`;
      criticalInput.value = '1';
      criticalText.textContent = picker.dataset.criticalLabel || '';
      criticalLabel.append(criticalInput, criticalText);
      remove.type = 'button';
      remove.dataset.competencyRemoveFunction = '';
      remove.setAttribute('aria-label', picker.dataset.removeLabel || 'Remove');
      remove.textContent = '×';
      hidden.type = 'hidden';
      hidden.name = 'function_pairs[]';
      hidden.value = pair;
      item.append(label, criticalLabel, remove, hidden);
      list.appendChild(item);
      sectionSearch.value = '';
      functionSearch.value = '';
      functionSearch.disabled = true;
      selectedSectionId = '';
      selectedPair = '';
      hide(sectionSuggestions);
      hide(functionSuggestions);
    });

    list.addEventListener('click', (event) => {
      if (event.target.closest('[data-competency-remove-function]')) {
        event.target.closest('.competency-pair-picker__item')?.remove();
        if (selectedSectionId) {
          filter(functionSearch, functionSuggestions, availableFunction);
        }
      }
    });

  });
};

const initFunctionCompetencyPickers = () => {
  document.querySelectorAll('[data-function-competency-picker]').forEach((picker) => {
    const search = picker.querySelector('[data-function-competency-search]');
    const suggestions = picker.querySelector('[data-function-competency-suggestions]');
    const addButton = picker.querySelector('[data-function-competency-add]');
    const list = picker.querySelector('[data-function-competency-list]');
    const matrix = picker.querySelector('[data-function-competency-matrix]');
    const functionDepartments = picker.closest('form')?.querySelector('select[name="department_ids[]"]');

    if (!search || !suggestions || !addButton || !list || !matrix || !functionDepartments) {
      return;
    }

    let selectedCompetencyId = '';
    const assignments = new Map();
    const competencyLabels = new Map(
      Array.from(suggestions.querySelectorAll('[data-competency-id]')).map((suggestion) => [
        suggestion.dataset.competencyId,
        suggestion.textContent.trim(),
      ]),
    );

    list.querySelectorAll('.function-competency-picker__initial').forEach((input) => {
      const [departmentId, competencyId, critical] = input.value.split(':');
      if (departmentId && competencyId) {
        assignments.set(`${departmentId}:${competencyId}`, { departmentId, competencyId, critical: critical === '1' });
      }
    });
    list.remove();

    const selectedDepartments = () => Array.from(functionDepartments.selectedOptions).map((option) => ({
      id: option.value,
      label: option.textContent.trim(),
    }));

    const renderMatrix = () => {
      const departments = selectedDepartments();
      const departmentIds = new Set(departments.map((department) => department.id));
      Array.from(assignments.keys()).forEach((key) => {
        if (!departmentIds.has(assignments.get(key).departmentId)) {
          assignments.delete(key);
        }
      });

      matrix.replaceChildren();
      const competencyIds = Array.from(new Set(Array.from(assignments.values()).map((assignment) => assignment.competencyId)));
      if (departments.length === 0 || competencyIds.length === 0) {
        matrix.classList.toggle('function-competency-picker__matrix--empty', competencyIds.length === 0);
        return;
      }

      const table = document.createElement('table');
      const head = document.createElement('thead');
      const headRow = document.createElement('tr');
      const competencyHead = document.createElement('th');
      competencyHead.textContent = picker.dataset.competencyLabel || 'Competency';
      headRow.appendChild(competencyHead);
      departments.forEach((department) => {
        const th = document.createElement('th');
        th.textContent = department.label;
        headRow.appendChild(th);
      });
      const actionHead = document.createElement('th');
      actionHead.textContent = '';
      headRow.appendChild(actionHead);
      head.appendChild(headRow);
      table.appendChild(head);

      const body = document.createElement('tbody');
      competencyIds.forEach((competencyId) => {
        const row = document.createElement('tr');
        const name = document.createElement('th');
        name.scope = 'row';
        name.textContent = competencyLabels.get(competencyId) || competencyId;
        row.appendChild(name);

        departments.forEach((department) => {
          const cell = document.createElement('td');
          const assignment = assignments.get(`${department.id}:${competencyId}`) || {
            departmentId: department.id,
            competencyId,
            critical: false,
          };
          assignments.set(`${department.id}:${competencyId}`, assignment);
          const checkbox = document.createElement('input');
          checkbox.type = 'checkbox';
          checkbox.checked = assignment.critical;
          checkbox.className = 'form-check-input';
          checkbox.dataset.functionCompetencyCritical = '';
          checkbox.setAttribute('aria-label', `${name.textContent} / ${department.label}`);
          checkbox.addEventListener('change', () => {
            assignment.critical = checkbox.checked;
            renderMatrix();
          });
          const hidden = document.createElement('input');
          hidden.type = 'hidden';
          hidden.name = 'competency_assignments[]';
          hidden.value = `${department.id}:${competencyId}:${assignment.critical ? 1 : 0}`;
          cell.append(checkbox, hidden);
          row.appendChild(cell);
        });

        const actionCell = document.createElement('td');
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.dataset.functionCompetencyRemove = competencyId;
        remove.setAttribute('aria-label', picker.dataset.removeLabel || 'Remove');
        remove.textContent = '×';
        actionCell.appendChild(remove);
        row.appendChild(actionCell);
        body.appendChild(row);
      });
      table.appendChild(body);
      matrix.appendChild(table);
    };

    const hideSuggestions = () => {
      suggestions.hidden = true;
    };

    const filterSuggestions = () => {
      if (search.disabled) {
        suggestions.hidden = true;
        return;
      }
      const query = search.value.trim().toLocaleLowerCase();
      let visibleCount = 0;
      suggestions.querySelectorAll('[data-competency-id]').forEach((suggestion) => {
        const visible = query === '' || suggestion.textContent.toLocaleLowerCase().includes(query);
        suggestion.hidden = !visible;
        if (visible) {
          visibleCount += 1;
        }
      });
      suggestions.hidden = visibleCount === 0;
    };

    search.addEventListener('input', () => {
      selectedCompetencyId = '';
      filterSuggestions();
    });
    search.addEventListener('focus', filterSuggestions);
    suggestions.addEventListener('click', (event) => {
      const suggestion = event.target.closest('[data-competency-id]');
      if (!suggestion) {
        return;
      }
      selectedCompetencyId = suggestion.dataset.competencyId || '';
      search.value = suggestion.textContent.trim();
      hideSuggestions();
    });
    document.addEventListener('click', (event) => {
      if (!picker.contains(event.target)) {
        hideSuggestions();
      }
    });

    addButton.addEventListener('click', () => {
      const competencyId = selectedCompetencyId;
      const departments = selectedDepartments();

      if (!competencyId || departments.length === 0 || Array.from(assignments.values()).some((assignment) => assignment.competencyId === competencyId)) {
        return;
      }

      departments.forEach((department) => {
        assignments.set(`${department.id}:${competencyId}`, { departmentId: department.id, competencyId, critical: false });
      });
      renderMatrix();
      search.value = '';
      selectedCompetencyId = '';
      hideSuggestions();
    });

    matrix.addEventListener('click', (event) => {
      const remove = event.target.closest('[data-function-competency-remove]');
      if (remove) {
        Array.from(assignments.keys()).forEach((key) => {
          if (assignments.get(key).competencyId === remove.dataset.functionCompetencyRemove) {
            assignments.delete(key);
          }
        });
        renderMatrix();
      }
    });

    functionDepartments.addEventListener('change', renderMatrix);
    renderMatrix();
  });
};

const initCompetencyTableFilters = (table, dataTable) => {
  const filters = table.closest('.module-panel')?.querySelector('[data-competency-filters]');
  if (!filters) {
    return;
  }

  const searchCell = table.closest('.module-panel')?.querySelector('.dt-layout-row:first-child .dt-layout-cell.dt-layout-start');
  if (searchCell) {
    searchCell.classList.add('competency-table-toolbar');
    searchCell.appendChild(filters);
  }

  const state = { department: '', section: '', function: '' };
  const inputs = {};
  const refreshers = {};
  const hide = (suggestions) => {
    suggestions.hidden = true;
  };
  const hideOtherSuggestions = (current) => {
    filters.querySelectorAll('.admin-smart-search__suggestions').forEach((suggestions) => {
      if (suggestions !== current) {
        hide(suggestions);
      }
    });
  };

  const draw = () => dataTable.draw(false);

  const isAllowedByParent = (key, suggestion) => {
    if (key === 'section' && state.department !== '') {
      return suggestion.dataset.departmentId === state.department;
    }
    if (key === 'function') {
      const departmentIds = (suggestion.dataset.departmentIds || '').split(',').filter(Boolean);
      const sectionIds = (suggestion.dataset.sectionIds || '').split(',').filter(Boolean);
      return (state.department === '' || departmentIds.includes(state.department))
        && (state.section === '' || sectionIds.includes(state.section));
    }
    return true;
  };

  const clearDependentFilters = () => {
    if (state.section && !Array.from(filters.querySelectorAll('[data-competency-filter-suggestions="section"] [data-filter-id]')).some((suggestion) => suggestion.dataset.filterId === state.section && isAllowedByParent('section', suggestion))) {
      state.section = '';
      if (inputs.section) inputs.section.value = '';
    }
    if (state.function && !Array.from(filters.querySelectorAll('[data-competency-filter-suggestions="function"] [data-filter-id]')).some((suggestion) => suggestion.dataset.filterId === state.function && isAllowedByParent('function', suggestion))) {
      state.function = '';
      if (inputs.function) inputs.function.value = '';
    }
  };

  DataTable.ext.search.push((settings, searchData, rowIndex) => {
    if (settings.nTable !== table) {
      return true;
    }
    const row = settings.aoData[rowIndex]?.nTr;
    if (!row) {
      return true;
    }
    if (state.department !== '') {
      const departmentIds = (row.dataset.filterDepartmentIds || '').split(',').filter(Boolean);
      if (!departmentIds.includes(state.department)) {
        return false;
      }
    }
    const pairs = JSON.parse(row.dataset.filterPairs || '[]');

    return ['section', 'function'].every((key) => state[key] === '' || pairs.some((pair) => String(pair[`${key}_id`]) === state[key]));
  });

  filters.querySelectorAll('[data-competency-filter]').forEach((input) => {
    const key = input.dataset.competencyFilter;
    inputs[key] = input;
    const suggestions = filters.querySelector(`[data-competency-filter-suggestions="${key}"]`);
    if (!suggestions) {
      return;
    }

    const filterSuggestions = () => {
      if (input.disabled) {
        suggestions.hidden = true;
        return;
      }
      const query = input.value.trim().toLocaleLowerCase();
      let visibleCount = 0;
      suggestions.querySelectorAll('[data-filter-id]').forEach((suggestion) => {
        const visible = isAllowedByParent(key, suggestion)
          && (query === '' || suggestion.textContent.toLocaleLowerCase().includes(query));
        suggestion.hidden = !visible;
        if (visible) {
          visibleCount += 1;
        }
      });
      suggestions.hidden = visibleCount === 0;
    };
    refreshers[key] = filterSuggestions;

    input.addEventListener('focus', () => {
      hideOtherSuggestions(suggestions);
      filterSuggestions();
    });
    input.addEventListener('input', () => {
      state[key] = '';
      if (key === 'department') {
        clearDependentFilters();
        refreshers.section?.();
        refreshers.function?.();
        hide(filters.querySelector('[data-competency-filter-suggestions="section"]'));
        hide(filters.querySelector('[data-competency-filter-suggestions="function"]'));
      }
      if (key === 'section') {
        state.function = '';
        if (inputs.function) inputs.function.value = '';
        refreshers.function?.();
        hideOtherSuggestions(suggestions);
        hide(filters.querySelector('[data-competency-filter-suggestions="function"]'));
      }
      filterSuggestions();
      draw();
    });
    suggestions.addEventListener('click', (event) => {
      const suggestion = event.target.closest('[data-filter-id]');
      if (!suggestion) {
        return;
      }
      state[key] = suggestion.dataset.filterId || '';
      input.value = suggestion.textContent.trim();
      if (key === 'department') {
        clearDependentFilters();
        refreshers.section?.();
        refreshers.function?.();
        hide(filters.querySelector('[data-competency-filter-suggestions="section"]'));
        hide(filters.querySelector('[data-competency-filter-suggestions="function"]'));
      }
      if (key === 'section') {
        state.function = '';
        if (inputs.function) inputs.function.value = '';
        refreshers.function?.();
        hideOtherSuggestions(suggestions);
        hide(filters.querySelector('[data-competency-filter-suggestions="function"]'));
      }
      hide(suggestions);
      draw();
    });
  });

  document.addEventListener('click', (event) => {
    if (!filters.contains(event.target)) {
      filters.querySelectorAll('.admin-smart-search__suggestions').forEach(hide);
    }
  });
};

const initCompetencyFunctionCells = () => {
  document.querySelectorAll('[data-competency-functions-toggle]').forEach((toggle) => {
    toggle.addEventListener('click', () => {
      const list = toggle.parentElement?.querySelector('[data-competency-functions-list]');
      if (!list) {
        return;
      }
      const expanded = toggle.getAttribute('aria-expanded') === 'true';
      toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
      list.hidden = expanded;
    });
  });
};

const initDataTableColumnFilters = (table, dataTable) => {
  table.querySelectorAll('[data-column-filter]').forEach((input) => {
    const columnIndex = Number(input.dataset.columnFilter);
    input.addEventListener('click', (event) => event.stopPropagation());
    input.addEventListener('keydown', (event) => event.stopPropagation());
    input.addEventListener('input', () => dataTable.column(columnIndex).search(input.value).draw());
  });
};

const syncDataTableStickyHeader = (table) => {
  const headerRows = Array.from(table.tHead?.rows || []);
  const headingRow = headerRows.find((row) => !row.classList.contains('admin-table__column-filters'));
  const filterRow = headerRows.find((row) => row.classList.contains('admin-table__column-filters'));

  if (!headingRow || !filterRow) {
    return;
  }

  const height = Math.ceil(headingRow.getBoundingClientRect().height);
  if (height > 0) {
    table.style.setProperty('--admin-table-header-row-height', `${height}px`);
  }
};

const ensureDataTableColumnFilters = (table, locale) => {
  if (table.dataset.noColumnFilters === 'true' || table.querySelector('[data-column-filter]')) {
    return;
  }

  const header = table.tHead?.querySelector('tr');
  if (!header) {
    return;
  }

  const filterRow = document.createElement('tr');
  filterRow.className = 'admin-table__column-filters';
  const translation = dataTableTranslations[locale] || dataTableTranslations.ru;

  Array.from(header.cells).forEach((cell, index) => {
    const filterCell = document.createElement('th');
    filterCell.dataset.columnFilterCell = String(index);

    if (cell.classList.contains('no-sort')) {
      filterCell.classList.add('no-sort');
    } else {
      const input = document.createElement('input');
      input.className = 'form-control';
      input.type = 'search';
      input.dataset.columnFilter = String(index);
      input.placeholder = cell.textContent.trim() || translation.columnFilter;
      input.title = input.placeholder;
      input.setAttribute('aria-label', cell.textContent.trim());
      filterCell.appendChild(input);
    }

    filterRow.appendChild(filterCell);
  });

  table.tHead.appendChild(filterRow);
};

const initCatalogRelationDepartmentFilter = (table, dataTable) => {
  const filter = table.closest('.module-panel')?.querySelector('[data-catalog-department-filter]');
  if (!filter) {
    return;
  }

  DataTable.ext.search.push((settings, searchData, rowIndex) => {
    if (settings.nTable !== table || filter.value === '') {
      return true;
    }

    const row = settings.aoData[rowIndex]?.nTr;
    const departmentIds = (row?.dataset.filterDepartmentIds || '').split(',').filter(Boolean);

    return departmentIds.includes(filter.value);
  });

  filter.addEventListener('change', () => dataTable.draw(false));
};

const initCompetencyColumnFilters = (table, dataTable) => {
  const filters = table.closest('.module-panel')?.querySelector('[data-competency-column-filters]');
  if (!filters) {
    return;
  }

  filters.querySelectorAll('[data-competency-column-filter]').forEach((input) => {
    const columnIndex = Number(input.dataset.competencyColumnFilter);
    input.addEventListener('input', () => dataTable.column(columnIndex).search(input.value).draw());
  });
};

const dataTableInitialOrder = (table) => {
  const rowReorderDataSrc = Number(table.dataset.rowReorderDataSrc);

  if (Number.isInteger(rowReorderDataSrc)) {
    return [[rowReorderDataSrc, 'asc']];
  }

  return undefined;
};

const rowIdsInCurrentOrder = (dataTable) => dataTable
  .rows({ order: 'applied', search: 'none' })
  .nodes()
  .toArray()
  .map((row) => row.dataset.rowId)
  .filter((id) => id);

const initDataTableRowReorder = (table, dataTable) => {
  const url = table.dataset.rowReorderUrl;

  if (!url) {
    return;
  }

  dataTable.on('row-reordered', () => {
    window.setTimeout(() => {
      saveDataTableRowOrder(url, dataTable);
    }, 0);
  });
};

const saveDataTableRowOrder = (url, dataTable) => {
  const body = new URLSearchParams();
  rowIdsInCurrentOrder(dataTable).forEach((id) => {
    body.append('department_ids[]', id);
  });

  fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
      'X-Requested-With': 'XMLHttpRequest',
    },
    body,
  }).then((response) => {
    if (!response.ok) {
      window.location.reload();
    }
  }).catch(() => {
    window.location.reload();
  });
};

const initCatalogColorPickers = () => {
  document.querySelectorAll('.admin-color-picker').forEach((picker) => {
    const customInput = picker.querySelector('.admin-color-picker__input');
    const presets = picker.querySelectorAll('input[name="color_preset"]');

    if (!customInput) {
      return;
    }

    presets.forEach((preset) => {
      preset.addEventListener('change', () => {
        if (preset.checked) {
          customInput.value = preset.value;
        }
      });
    });

    customInput.addEventListener('input', () => {
      presets.forEach((preset) => {
        preset.checked = preset.value.toLowerCase() === customInput.value.toLowerCase();
      });
    });
  });
};

document.addEventListener('DOMContentLoaded', () => {
  const locale = document.documentElement.lang || 'ru';

  initAdminSidebarToggle();
  initAutoSubmitForms();
  initCompetencyMatrixLiveSearch();
  initTrainingPlanModals();
  initEmployeePeriodForms();
  initEmployeeAssignmentForms();
  initCompetencyForms();
  initFunctionCompetencyPickers();
  initCompetencyFunctionCells();
  initCatalogColorPickers();

  document.querySelectorAll('.js-auto-dismiss-alert').forEach((alertElement) => {
    window.setTimeout(() => {
      alertElement.classList.add('admin-alert--hiding');

      window.setTimeout(() => {
        Alert.getOrCreateInstance(alertElement).close();
      }, 180);
    }, 5000);
  });

  document.querySelectorAll('.js-data-table').forEach((table) => {
    if (DataTable.isDataTable(table)) {
      return;
    }

    ensureDataTableColumnFilters(table, locale);

    const hiddenColumns = (table.dataset.hiddenColumns || '').split(',').map((value) => value.trim()).filter((value) => value !== '').map((value) => Number(value)).filter((value) => Number.isInteger(value));
    const columnDefs = [
      {
        targets: 'no-sort',
        orderable: false,
        searchable: false,
      },
      {
        targets: 'admin-table__sort-order',
        visible: false,
        searchable: false,
      },
    ];
    if (hiddenColumns.length > 0) {
      columnDefs.push({ targets: hiddenColumns, visible: false });
    }

    const dataTableOptions = {
      pageLength: 10,
      lengthMenu: [10, 25, 50, 100],
      layout: {
        topStart: 'search',
        topEnd: 'pageLength',
        bottomStart: 'info',
        bottomEnd: 'paging',
      },
      searching: true,
      paging: true,
      ordering: true,
      info: true,
      columnDefs,
      rowReorder: table.dataset.rowReorderUrl ? {
        dataSrc: Number(table.dataset.rowReorderDataSrc),
        selector: '.admin-row-reorder-handle',
      } : false,
      language: dataTableTranslations[locale] || dataTableTranslations.ru,
    };
    const initialOrder = dataTableInitialOrder(table);
    if (initialOrder !== undefined) {
      dataTableOptions.order = initialOrder;
    }

    const dataTable = new DataTable(table, dataTableOptions);

    dataTables.set(table, dataTable);
    decorateDataTableSearch(table);
    if (table.matches('[data-competency-filter-table]')) {
      initCompetencyTableFilters(table, dataTable);
    }
    initDataTableColumnFilters(table, dataTable);
    initCatalogRelationDepartmentFilter(table, dataTable);
    initCompetencyColumnFilters(table, dataTable);
    initDataTableRowReorder(table, dataTable);
    syncDataTableStickyHeader(table);
    dataTable.on('draw', () => syncDataTableStickyHeader(table));
    window.addEventListener('resize', () => syncDataTableStickyHeader(table));
  });

  document.querySelectorAll('.employee-history-modal').forEach((modal) => {
    modal.addEventListener('shown.bs.modal', () => {
      modal.querySelectorAll('.js-data-table').forEach((table) => {
        if (DataTable.isDataTable(table)) {
          new DataTable(table).columns.adjust();
        }
      });
    });
  });

  document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', () => {
      const permissionTable = form.querySelector('.admin-permission-table');

      if (!permissionTable) {
        return;
      }

      const dataTable = dataTables.get(permissionTable);

      if (!dataTable) {
        return;
      }

      form.querySelectorAll('.js-datatable-submit-clone').forEach((clone) => clone.remove());

      dataTable.rows().every(function collectCheckedInputs() {
        const row = this.node();

        row.querySelectorAll('input[type="checkbox"]:checked').forEach((input) => {
          const clone = document.createElement('input');
          clone.className = 'js-datatable-submit-clone';
          clone.type = 'hidden';
          clone.name = input.name;
          clone.value = input.value;
          form.appendChild(clone);
        });
      });
    });
  });

  document.querySelectorAll('[data-open-modal="true"]').forEach((modalElement) => {
    Modal.getOrCreateInstance(modalElement).show();
  });

  document.querySelectorAll('.js-admin-multiselect').forEach(createCatalogMultiselect);
});

const appElement = document.getElementById('app');

if (appElement) {
  createApp(App).mount(appElement);
}
