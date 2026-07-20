/**
 * DOUBLE A SOLUTIONS - Corporate Interactive Scripts
 * Handles multi-page menu, dynamic service advisor, modal cards, FAQ accordion, cookie notice, and toast.
 */

document.addEventListener('DOMContentLoaded', () => {
    // Highlight active link in header nav based on current path
    const currentPath = window.location.pathname;
    document.querySelectorAll('.navlinks a').forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentPath || (href !== '/' && currentPath.startsWith(href))) {
            link.classList.add('active');
        } else {
            link.classList.remove('active');
        }
    });

    // 1. Language switcher dropdown
    const lang = document.getElementById('lang');
    const langBtn = document.getElementById('langBtn');
    const langMenu = document.getElementById('langMenu');

    if (lang && langBtn) {
        langBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            lang.classList.toggle('open');
            const expanded = lang.classList.contains('open');
            langBtn.setAttribute('aria-expanded', expanded);
        });

        // Close on outside click
        document.addEventListener('click', () => {
            lang.classList.remove('open');
            langBtn.setAttribute('aria-expanded', 'false');
        });

        // Language redirect
        if (langMenu) {
            langMenu.querySelectorAll('button').forEach(btn => {
                btn.addEventListener('click', () => {
                    const code = btn.getAttribute('data-lang');
                    if (code) {
                        const currentUrl = new URL(window.location.href);
                        currentUrl.searchParams.set('_locale', code);
                        window.location.href = currentUrl.toString();
                    }
                });
            });
        }
    }

    // 2. Mobile Menu Toggle
    const menuBtn = document.getElementById('menuBtn');
    const navlinks = document.getElementById('navlinks');

    if (menuBtn && navlinks) {
        menuBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            navlinks.classList.toggle('open');
            menuBtn.innerHTML = navlinks.classList.contains('open') ? '✕' : '☰';
        });

        document.addEventListener('click', (e) => {
            if (!navlinks.contains(e.target) && e.target !== menuBtn) {
                navlinks.classList.remove('open');
                menuBtn.innerHTML = '☰';
            }
        });
    }

    // 3. FAQ Accordion
    const faqItems = document.querySelectorAll('.faq-item');
    faqItems.forEach(item => {
        const question = item.querySelector('.faq-q');
        const answer = item.querySelector('.faq-a');
        if (question && answer) {
            question.addEventListener('click', () => {
                const isOpen = item.classList.contains('open');
                
                // Close other items
                faqItems.forEach(other => {
                    other.classList.remove('open');
                    const otherAnswer = other.querySelector('.faq-a');
                    if (otherAnswer) {
                        otherAnswer.style.maxHeight = null;
                        otherAnswer.style.paddingBottom = null;
                    }
                });

                if (!isOpen) {
                    item.classList.add('open');
                    answer.style.maxHeight = answer.scrollHeight + 'px';
                    answer.style.paddingBottom = '24px';
                }
            });
        }
       // 4. Bento Grid Service Advisor
    const bentoIndustries = document.querySelectorAll('#bentoIndustries .bento-opt-card');
    const bentoGoals = document.querySelectorAll('#bentoGoals .bento-opt-card');
    const bentoResultPanel = document.getElementById('bentoResultPanel');
    const bentoResultBody = document.getElementById('bentoResultBody');

    if (bentoIndustries.length > 0 && bentoGoals.length > 0 && bentoResultPanel && bentoResultBody) {
        let selectedIndustry = null;
        let selectedGoal = null;

        const advisorDb = {
            'agro_import': '<strong>Выбранное направление: Агросектор -> Импорт</strong><br><br>Для импорта СЗР и удобрений в Узбекистан требуется оформление государственной регистрации в Минсельхозе РУз. <br><br><strong>Шаги маршрута:</strong><br>1. Подготовка регистрационного досье и химико-токсикологический анализ.<br>2. Организация и проведение полевых испытаний (длительность — 1 вегетационный период).<br>3. Токсиколого-гигиеническая экспертиза и утверждение регламентов применения.<br>4. Включение в государственный реестр разрешенных препаратов.',
            'agro_production': '<strong>Выбранное направление: Агросектор -> Производство</strong><br><br>Организация производства пестицидов и агрохимикатов в РУз подлежит строгому экологическому и промышленному лицензированию.<br><br><strong>Шаги маршрута:</strong><br>1. Проектирование завода и проведение государственной экологической экспертизы (ЗВОС).<br>2. Получение лицензии на работу с сильнодействующими ядовитыми веществами.<br>3. Разработка технических условий (ТС) на каждый выпускаемый препарат и госрегистрация.',
            'agro_export': '<strong>Выбранное направление: Агросектор -> Экспорт</strong><br><br>Экспорт агропродукции в страны ЕС и СНГ требует приведения системы качества к международным регламентам.<br><br><strong>Шаги маршрута:</strong><br>1. Аудит соответствия производства санитарным нормам стран назначения.<br>2. Получение фитосанитарного сертификата Агентства карантина растений РУз.<br>3. Приведение маркировки и упаковки продукции к требованиям регламентов импортера.',
            'agro_iso': '<strong>Выбранное направление: Агросектор -> Сертификация ISO</strong><br><br>Внедрение стандартов менеджмента для лабораторий и агрохолдингов.<br><br><strong>Шаги маршрута:</strong><br>1. Диагностический аудит бизнес-процессов компании.<br>2. Разработка руководств по качеству и документированных процедур (ISO 9001 / ISO 22000).<br>3. Обучение внутренних аудиторов и проведение пробных проверок.<br>4. Сертификационный аудит с участием признанного органа.',
            
            'food_import': '<strong>Выбранное направление: Пищевой бизнес -> Импорт</strong><br><br>Импорт продуктов питания и биологически активных добавок (БАД) требует подтверждения безопасности СЭС РУз.<br><br><strong>Шаги маршрута:</strong><br>1. Анализ состава продукта на соответствие СанПиН РУз (запрещенные ингредиенты).<br>2. Получение санитарно-эпидемиологического заключения на продукцию.<br>3. Проведение испытаний на показатели безопасности и оформление сертификата соответствия.<br>4. Разработка и согласование стикеров на узбекском языке.',
            'food_production': '<strong>Выбранное направление: Пищевой бизнес -> Производство</strong><br><br>Пищевое производство в РУз должно функционировать на принципах HACCP (ISO 22000).<br><br><strong>Шаги маршрута:</strong><br>1. Гигиеническое обследование производственного цеха органами СЭС.<br>2. Внедрение процедур ХАССП, разработка критических контрольных точек (ККТ).<br>3. Проведение испытаний готовой продукции и сырья в аккредитованной лаборатории.<br>4. Государственная регистрация продукции в СЭС.',
            'food_export': '<strong>Выбранное направление: Пищевой бизнес -> Экспорт</strong><br><br>Вывоз продуктов питания на рынки ЕС требует соответствия регламентам безопасности (FSSC 22000).<br><br><strong>Шаги маршрута:</strong><br>1. Анализ готовности производства к европейским аудитам пищевой безопасности.<br>2. Разработка спецификаций на продукцию и проверка упаковки.<br>3. Оформление сертификатов происхождения (СТ-1) и гигиенических заключений.<br>4. Сопровождение экспортной таможенной очистки.',
            'food_iso': '<strong>Выбранное направление: Пищевой бизнес -> Сертификация ISO</strong><br><br>Внедрение стандарта безопасности пищевой продукции ISO 22000 / FSSC 22000.<br><br><strong>Шаги маршрута:</strong><br>1. Диагностика предприятия, картирование процессов цехов.<br>2. Обучение рабочей группы ХАССП принципам стандарта.<br>3. Разработка СОП, инструкций личной гигиены, программ предварительных условий.<br>4. Успешная внешняя сертификация.',
            
            'cosmetic_import': '<strong>Выбранное направление: Косметика -> Импорт</strong><br><br>Поставки косметической продукции требуют обязательного подтверждения соответствия стандартам ТС/РУз.<br><br><strong>Шаги маршрута:</strong><br>1. Экспертиза технического досье, рецептур и сертификатов качества завода-изготовителя.<br>2. Оформление санитарно-эпидемиологического заключения на серию.<br>3. Регистрация декларации о соответствии продукции требованиям безопасности.',
            'cosmetic_production': '<strong>Выбранное направление: Косметика -> Производство</strong><br><br>Организация парфюмерно-косметического производства на базе стандартов GMP (ISO 22716).<br><br><strong>Шаги маршрута:</strong><br>1. Разработка технологического регламента производства косметики.<br>2. Внедрение требований GMP к чистоте зон, вентиляции и технологическому оборудованию.<br>3. Проведение лабораторных испытаний каждой партии готовой продукции.',
            'cosmetic_export': '<strong>Выбранное направление: Косметика -> Экспорт</strong><br><br>Экспорт косметики требует сертификата свободной продажи (Free Sale) и деклараций соответствия.<br><br><strong>Шаги маршрута:</strong><br>1. Оформление сертификата происхождения и качества продукции.<br>2. Получение заключения об экологической безопасности ингредиентов.<br>3. Разработка упаковки на языках стран экспорта.',
            'cosmetic_iso': '<strong>Выбранное направление: Косметика -> Сертификация ISO</strong><br><br>Внедрение стандарта ISO 22716 (GMP для косметической промышленности).<br><br><strong>Шаги маршрута:</strong><br>1. Аудит инфраструктуры предприятия на соответствие стандартам чистоты.<br>2. Создание процедур валидации очистки оборудования и контроля партий.<br>3. Проведение внутренних проверок персонала.<br>4. Внешний аудит соответствия GMP.',
            
            'vet_import': '<strong>Выбранное направление: Ветеринария -> Импорт</strong><br><br>Импорт ветеринарных препаратов подлежит строгому контролю со стороны Комитета ветеринарии РУз.<br><br><strong>Шаги маршрута:</strong><br>1. Подача заявки на регистрацию ветеринарного препарата в Научно-контрольный институт.<br>2. Лабораторная экспертиза качества препарата и оценка токсичности.<br>3. Получение регистрационного удостоверения Комитета ветеринарии РУз.<br>4. Получение ветеринарного разрешения на ввоз каждой партии.',
            'vet_production': '<strong>Выбранное направление: Ветеринария -> Производство</strong><br><br>Производство ветеринарных лекарств подлежит обязательному ветеринарно-санитарному лицензированию.<br><br><strong>Шаги маршрута:</strong><br>1. Проектирование зон производства по правилам ветеринарного GMP.<br>2. Получение лицензии Комитета ветеринарии на право ведения деятельности.<br>3. Разработка методик контроля качества в производственной лаборатории.<br>4. Регистрация выпускаемых ветеринарных препаратов.',
            'vet_export': '<strong>Выбранное направление: Ветеринария -> Экспорт</strong><br><br>Экспорт ветеринарных товаров требует международного ветеринарного сертификата.<br><br><strong>Шаги маршрута:</strong><br>1. Включение предприятия в Реестр экспортеров Комитета ветеринарии РУз.<br>2. Проведение ветеринарного осмотра экспортируемой партии.<br>3. Подготовка и заверение международного ветеринарного сертификата.',
            'vet_iso': '<strong>Выбранное направление: Ветеринария -> Сертификация ISO</strong><br><br>Внедрение систем ISO/IEC 17025 для испытательных лабораторий ветеринарного контроля.<br><br><strong>Шаги маршрута:</strong><br>1. Оценка материально-технической базы ветеринарной лаборатории.<br>2. Внедрение системы качества, разработка процедур градуировки и поверки.<br>3. Проведение раундов межлабораторных сличительных испытаний (МСИ).<br>4. Прохождение аккредитации.',
        };

        const updateBentoResult = () => {
            if (selectedIndustry && selectedGoal) {
                const key = `${selectedIndustry}_${selectedGoal}`;
                const text = advisorDb[key] || 'Данный маршрут находится в процессе разработки. Обратитесь к эксперту за детальной консультацией.';
                bentoResultBody.innerHTML = `
                    <div class="bento-result-content">
                        ${text}
                        <div style="margin-top: 30px;">
                            <a class="btn primary" href="/kontakty">Обсудить этот проект с координатором</a>
                        </div>
                    </div>
                `;
                bentoResultBody.style.display = 'block';
                const placeholder = bentoResultPanel.querySelector('.bento-result-placeholder');
                if (placeholder) placeholder.style.display = 'none';
            }
        };

        bentoIndustries.forEach(card => {
            card.addEventListener('click', () => {
                bentoIndustries.forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');
                selectedIndustry = card.getAttribute('data-value');
                updateBentoResult();
            });
        });

        bentoGoals.forEach(card => {
            card.addEventListener('click', () => {
                bentoGoals.forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');
                selectedGoal = card.getAttribute('data-value');
                updateBentoResult();
            });
        });
    }

    // 5. Quick Selector Modal dialog
    const quickButtons = document.querySelectorAll('.quick');
    const modal = document.getElementById('infoModal');

    if (modal) {
        const modalTitle = document.getElementById('modalTitle');
        const modalContent = document.getElementById('modalContent');
        const closeBtn = modal.querySelector('.modal-close');

        const selectorDb = {
            'market': {
                title: 'Выход на рынок Узбекистана',
                content: `
                    <p>Разработка дорожной карты запуска коммерческой деятельности и юридической схемы работы в РУз.</p>
                    <h3 style="margin: 25px 0 12px; font-family: var(--serif); font-size: 20px;">Что входит в услугу:</h3>
                    <ul style="margin: 0 0 20px; padding-left: 20px; line-height: 1.6; display: grid; gap: 8px;">
                      <li><b>Маркетинговые исследования:</b> Оценка рынка, анализ ценового позиционирования конкурентов, выявление ключевых дистрибьюторов.</li>
                      <li><b>Анализ барьеров:</b> Расчет таможенных пошлин, акцизов, НДС и обязательных платежей при импорте.</li>
                      <li><b>Разработка схемы работы:</b> Выбор формы присутствия (дочернее общество, СП, представительство) и налогового режима.</li>
                      <li><b>Дорожная карта:</b> Подробный график запуска с указанием этапов, сроков и бюджетов.</li>
                    </ul>
                    <div class="detail-grid">
                        <div class="detail"><b>Сроки:</b><span>От 15 до 30 рабочих дней</span></div>
                        <div class="detail"><b>Результат:</b><span>Практический план коммерческой деятельности</span></div>
                    </div>
                `
            },
            'permits': {
                title: 'Разрешительные документы',
                content: `
                    <p>Сопровождение государственной регистрации и сертификации регулируемой продукции под ключ в уполномоченных ведомствах Узбекистана.</p>
                    <h3 style="margin: 25px 0 12px; font-family: var(--serif); font-size: 20px;">Ключевые направления:</h3>
                    <ul style="margin: 0 0 20px; padding-left: 20px; line-height: 1.6; display: grid; gap: 8px;">
                      <li><b>Удобрения и пестициды (СЗР):</b> Подача заявок в Минсельхоз РУз, координация полевых тестов, токсикологическая экспертиза.</li>
                      <li><b>БАДы и продукты питания:</b> Получение гигиенического заключения СЭС РУз, проведение лабораторных тестов на показатели безопасности.</li>
                      <li><b>Парфюмерия и косметика:</b> Оформление санитарно-эпидемиологических заключений и регистрация деклараций соответствия.</li>
                      <li><b>Ветеринарные препараты:</b> Регистрация в Комитете ветеринарии и развития животноводства РУз.</li>
                    </ul>
                    <div class="detail-grid">
                        <div class="detail"><b>Экспертиза:</b><span>Аудит досье производителя перед подачей</span></div>
                        <div class="detail"><b>Результат:</b><span>Официальное регистрационное свидетельство</span></div>
                    </div>
                `
            },
            'export': {
                title: 'Экспортное сопровождение',
                content: `
                    <p>Оформление пакета документов для легальных поставок товаров из Узбекистана в СНГ и Евросоюз.</p>
                    <h3 style="margin: 25px 0 12px; font-family: var(--serif); font-size: 20px;">Основные этапы подготовки:</h3>
                    <ul style="margin: 0 0 20px; padding-left: 20px; line-height: 1.6; display: grid; gap: 8px;">
                      <li><b>Аудит упаковки и маркировки:</b> Адаптация этикеток под требования технических регламентов ТС или ЕС (языки, знаки соответствия, пищевая ценность).</li>
                      <li><b>Оформление сертификатов:</b> Помощь в получении сертификатов происхождения (СТ-1, Form A), фитосанитарных и ветеринарных документов.</li>
                      <li><b>Подготовка к проверкам:</b> Предварительный аудит производственных линий перед государственным инспектированием.</li>
                    </ul>
                    <div class="detail-grid">
                        <div class="detail"><b>Документы:</b><span>Сертификаты происхождения, фито и вет</span></div>
                        <div class="detail"><b>Результат:</b><span>Беспрепятственное прохождение таможни</span></div>
                    </div>
                `
            },
            'iso': {
                title: 'Стандарты качества & Аккредитация',
                content: `
                    <p>Разработка, внедрение и сопровождение сертификации систем управления качеством.</p>
                    <h3 style="margin: 25px 0 12px; font-family: var(--serif); font-size: 20px;">Наши направления работы:</h3>
                    <ul style="margin: 0 0 20px; padding-left: 20px; line-height: 1.6; display: grid; gap: 8px;">
                      <li><b>Пищевая безопасность:</b> Разработка и внедрение принципов ХАССП (HACCP / ISO 22000 / FSSC 22000).</li>
                      <li><b>Косметическое GMP:</b> Внедрение стандарта ISO 22716 на косметических производствах.</li>
                      <li><b>Аккредитация лабораторий:</b> Подготовка испытательных лабораторий к аккредитации по международному стандарту ISO/IEC 17025.</li>
                    </ul>
                    <div class="detail-grid">
                        <div class="detail"><b>Стандарты:</b><span>ISO 9001, ISO 22000, ISO 22716</span></div>
                        <div class="detail"><b>Результат:</b><span>Успешный внешний аудит и сертификат соответствия</span></div>
                    </div>
                `
            }
        };

        const openServiceModal = (val) => {
            const data = selectorDb[val];
            if (data) {
                modalTitle.textContent = data.title;
                modalContent.innerHTML = data.content;
                modal.classList.add('show');
            }
        };

        window.closeModal = () => {
            modal.classList.remove('show');
            if (window.location.hash.startsWith('#service-')) {
                history.pushState("", document.title, window.location.pathname + window.location.search);
            }
        };

        closeBtn.addEventListener('click', window.closeModal);
        modal.addEventListener('click', (e) => {
            if (e.target === modal) window.closeModal();
        });

        // Open by click
        document.querySelectorAll('.quick, [href^="#service-"]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const href = btn.getAttribute('href');
                const val = btn.getAttribute('data-value') || (href && href.startsWith('#service-') ? href.replace('#service-', '') : null);
                if (val && selectorDb[val]) {
                    e.preventDefault();
                    openServiceModal(val);
                    window.location.hash = 'service-' + val;
                }
            });
        });

        // Open by hash on page load
        const handleHash = () => {
            const hash = window.location.hash;
            if (hash.startsWith('#service-')) {
                const val = hash.replace('#service-', '');
                if (selectorDb[val]) {
                    openServiceModal(val);
                }
            }
        };

        window.addEventListener('hashchange', handleHash);
        handleHash();
    }

    // 6. Cookie notice popup
    const cookieDiv = document.getElementById('cookieBar');
    if (cookieDiv) {
        if (!localStorage.getItem('cookieConsent')) {
            setTimeout(() => {
                cookieDiv.classList.add('show');
            }, 2000);
        }

        const acceptBtn = document.getElementById('cookieAccept');
        const declineBtn = document.getElementById('cookieDecline');

        if (acceptBtn) {
            acceptBtn.addEventListener('click', () => {
                localStorage.setItem('cookieConsent', 'accepted');
                cookieDiv.classList.remove('show');
                showToast('Файлы cookie приняты');
            });
        }
        if (declineBtn) {
            declineBtn.addEventListener('click', () => {
                localStorage.setItem('cookieConsent', 'declined');
                cookieDiv.classList.remove('show');
            });
        }
    }

    // 7. Toast Utilities
    const toastDiv = document.getElementById('toast');
    window.showToast = (msg) => {
        if (toastDiv) {
            toastDiv.textContent = msg;
            toastDiv.classList.add('show');
            setTimeout(() => {
                toastDiv.classList.remove('show');
            }, 3000);
        }
    };

    // 8. Cases categories filter
    const filters = document.querySelectorAll('.filter');
    const cases = document.querySelectorAll('.case');

    filters.forEach(filter => {
        filter.addEventListener('click', () => {
            filters.forEach(f => f.classList.remove('active'));
            filter.classList.add('active');

            const category = filter.getAttribute('data-filter');

            cases.forEach(c => {
                c.style.transition = 'opacity 0.25s, transform 0.25s';
                if (category === 'all' || c.getAttribute('data-category') === category) {
                    c.style.display = 'flex';
                    setTimeout(() => {
                        c.style.opacity = '1';
                        c.style.transform = 'scale(1)';
                    }, 50);
                } else {
                    c.style.opacity = '0';
                    c.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        c.style.display = 'none';
                    }, 250);
                }
            });
        });
    });
});
