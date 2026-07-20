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
    });

    // 4. Premium Service Advisor Wizard
    const serviceAdvisor = document.getElementById('serviceAdvisor');
    if (serviceAdvisor) {
        let currentStep = 1;
        const steps = serviceAdvisor.querySelectorAll('.advisor-step');
        const prevBtn = document.getElementById('advisorPrevBtn');
        const nextBtn = document.getElementById('advisorNextBtn');
        const controls = document.getElementById('advisorControls');
        const resetBtn = document.getElementById('advisorResetBtn');
        const resultText = document.getElementById('advisorResultText');

        let selection = {
            industry: null,
            goal: null
        };

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

        const updateSteps = () => {
            steps.forEach((step, idx) => {
                if (idx + 1 === currentStep) {
                    step.classList.add('active');
                } else {
                    step.classList.remove('active');
                }
            });

            if (currentStep === 1) {
                prevBtn.style.visibility = 'hidden';
                nextBtn.textContent = 'Продолжить';
                nextBtn.disabled = !selection.industry;
                controls.style.display = 'flex';
            } else if (currentStep === 2) {
                prevBtn.style.visibility = 'visible';
                nextBtn.textContent = 'Сформировать маршрут';
                nextBtn.disabled = !selection.goal;
                controls.style.display = 'flex';
            } else if (currentStep === 3) {
                controls.style.display = 'none';
                const key = `${selection.industry}_${selection.goal}`;
                const text = advisorDb[key] || 'Данный маршрут находится в процессе разработки. Обратитесь к эксперту за детальной консультацией.';
                resultText.innerHTML = text;
            }
        };

        // Option selection
        serviceAdvisor.querySelectorAll('.advisor-step[data-step="1"] .advisor-opt').forEach(opt => {
            opt.addEventListener('click', () => {
                serviceAdvisor.querySelectorAll('.advisor-step[data-step="1"] .advisor-opt').forEach(o => o.classList.remove('selected'));
                opt.classList.add('selected');
                selection.industry = opt.getAttribute('data-value');
                nextBtn.disabled = false;
            });
        });

        serviceAdvisor.querySelectorAll('.advisor-step[data-step="2"] .advisor-opt').forEach(opt => {
            opt.addEventListener('click', () => {
                serviceAdvisor.querySelectorAll('.advisor-step[data-step="2"] .advisor-opt').forEach(o => o.classList.remove('selected'));
                opt.classList.add('selected');
                selection.goal = opt.getAttribute('data-value');
                nextBtn.disabled = false;
            });
        });

        nextBtn.addEventListener('click', () => {
            if (currentStep < 3) {
                currentStep++;
                updateSteps();
            }
        });

        prevBtn.addEventListener('click', () => {
            if (currentStep > 1) {
                currentStep--;
                updateSteps();
            }
        });

        resetBtn.addEventListener('click', () => {
            currentStep = 1;
            selection = { industry: null, goal: null };
            serviceAdvisor.querySelectorAll('.advisor-opt').forEach(o => o.classList.remove('selected'));
            updateSteps();
        });

        updateSteps();
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
                    <p>Подготовка стратегии запуска коммерческой деятельности и импорта в Республику Узбекистан.</p>
                    <div class="detail-grid">
                        <div class="detail"><b>Сроки:</b><span>От 15 рабочих дней</span></div>
                        <div class="detail"><b>Анализ:</b><span>Конкуренты, цены и емкость рынка</span></div>
                        <div class="detail"><b>Логика:</b><span>Разработка дорожной карты запуска</span></div>
                        <div class="detail"><b>Результат:</b><span>Практический план коммерческой деятельности</span></div>
                    </div>
                `
            },
            'permits': {
                title: 'Разрешительные документы',
                content: `
                    <p>Сопровождение государственной регистрации и сертификации регулируемой продукции под ключ.</p>
                    <div class="detail-grid">
                        <div class="detail"><b>Проверка:</b><span>Аудит досье производителя перед подачей</span></div>
                        <div class="detail"><b>Испытания:</b><span>Токсикологические тесты и полевые испытания</span></div>
                        <div class="detail"><b>Реестр:</b><span>Внесение в реестр Минсельхоза, СЭС или Комитета вет</span></div>
                        <div class="detail"><b>Результат:</b><span>Официальное регистрационное свидетельство</span></div>
                    </div>
                `
            },
            'export': {
                title: 'Экспортное сопровождение',
                content: `
                    <p>Оформление пакета документов для легальных поставок товаров из Узбекистана в СНГ и Евросоюз.</p>
                    <div class="detail-grid">
                        <div class="detail"><b>Маркировка:</b><span>Подготовка этикеток по техническим регламентам</span></div>
                        <div class="detail"><b>Сертификация:</b><span>Оформление сертификатов СТ-1, фито и вет</span></div>
                        <div class="detail"><b>Аудит:</b><span>Проверка производства перед государственным инспектированием</span></div>
                        <div class="detail"><b>Результат:</b><span>Беспрепятственное прохождение таможни</span></div>
                    </div>
                `
            },
            'iso': {
                title: 'Стандарты качества & Аккредитация',
                content: `
                    <p>Разработка, внедрение и сопровождение сертификации систем управления качеством.</p>
                    <div class="detail-grid">
                        <div class="detail"><b>Стандарты:</b><span>ISO 9001, ISO 22000 (HACCP), ISO 22716 (GMP)</span></div>
                        <div class="detail"><b>Лаборатории:</b><span>Подготовка к аккредитации по ISO/IEC 17025</span></div>
                        <div class="detail"><b>Обучение:</b><span>Обучение аудиторов, разработка СОП и руководств</span></div>
                        <div class="detail"><b>Результат:</b><span>Успешный внешний аудит и сертификат соответствия</span></div>
                    </div>
                `
            }
        };

        window.closeModal = () => {
            modal.classList.remove('show');
        };

        closeBtn.addEventListener('click', window.closeModal);
        modal.addEventListener('click', (e) => {
            if (e.target === modal) window.closeModal();
        });

        quickButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const val = btn.getAttribute('data-value');
                const data = selectorDb[val];
                if (data) {
                    modalTitle.textContent = data.title;
                    modalContent.innerHTML = data.content;
                    modal.classList.add('show');
                }
            });
        });
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
