            </div><!-- /.container-fluid -->
            </section><!-- /.content -->
            </div><!-- /.content-wrapper -->

            <footer class="main-footer no-print d-flex flex-wrap justify-content-between align-items-center" style="gap: 12px;">
                <div style="color: #2e7d32;">
                    <strong>IG-FARDC @ 2026</strong> Tous droits réservés.
                </div>
                <div class="d-inline-block" style="color: #2e7d32;">
                    <b>Version</b> 1.0
                    <?php if (isset($_SESSION['user_nom'])): ?>
                        | <b>Connecté :</b> <?= htmlspecialchars($_SESSION['user_nom']) ?>
                    <?php endif; ?>
                </div>
            </footer>
            </div><!-- ./wrapper -->

            <!-- jQuery -->
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <!-- Bootstrap 5 Bundle -->
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
            <!-- AdminLTE -->
            <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
            <!-- Leaflet -->
            <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
            <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
            <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
            <script>
if (window.jQuery && $.fn.dataTable) {
    const ctrDataTableLanguage = {
        url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json',
        search: '<i class="fas fa-search"></i>',
        lengthMenu: 'Afficher _MENU_ éléments',
        info: 'Affichage de _START_ à _END_ sur _TOTAL_ éléments',
        infoEmpty: 'Aucune ligne',
        infoFiltered: '(filtré de _MAX_ éléments au total)',
        zeroRecords: 'Aucune ligne correspondante',
        paginate: {
            first: 'Premier',
            previous: 'Précédent',
            next: 'Suivant',
            last: 'Dernier'
        }
    };

    $.extend(true, $.fn.dataTable.defaults, {
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        autoWidth: false,
        orderCellsTop: true,
        responsive: false,
        language: ctrDataTableLanguage
    });

    window.CTRDataTableHelpers = window.CTRDataTableHelpers || {
        escapeRegex(value) {
            return String(value ?? '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        },
        highlightSearchTerm(data, searchTerm) {
            if (!searchTerm || !data) {
                return data;
            }

            const escapedSearch = this.escapeRegex(searchTerm);
            const searchRegex = new RegExp('(' + escapedSearch + ')', 'gi');

            if (typeof data !== 'string') {
                return data;
            }

            if (data.indexOf('<') !== -1 && data.indexOf('>') !== -1) {
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = data;
                const textContent = tempDiv.textContent || tempDiv.innerText || '';

                if (!textContent.match(searchRegex)) {
                    return data;
                }

                return data.replace(
                    new RegExp(this.escapeRegex(textContent), 'g'),
                    textContent.replace(searchRegex, '<mark>$1</mark>')
                );
            }

            return data.replace(searchRegex, '<mark>$1</mark>');
        },
        decorateFilterBlock(tableSelector, actionsHtml = '') {
            if (!tableSelector) {
                return;
            }

            const $filterDiv = $(tableSelector + '_filter');
            if (!$filterDiv.length || $filterDiv.data('ctr-enhanced')) {
                return;
            }

            $filterDiv.css({
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'flex-end',
                gap: '10px',
                flexWrap: 'wrap'
            });

            const $label = $filterDiv.find('label');
            $label.css({
                display: 'flex',
                alignItems: 'center',
                marginBottom: '0',
                flex: '0 1 auto'
            });

            $label.find('i').remove();
            $label.contents().filter(function () {
                return this.nodeType === 3;
            }).remove();

            if (!$filterDiv.children('.search-icon').length) {
                $('<i class="fas fa-search search-icon" style="color: #2e7d32; font-size: 1rem;"></i>').prependTo($filterDiv);
            }

            const $input = $label.find('input');
            $input.attr('placeholder', 'Rechercher dans le tableau...');

            if (actionsHtml && !$filterDiv.find('.action-buttons').length) {
                $filterDiv.append(actionsHtml);
            }

            $filterDiv.data('ctr-enhanced', true);
        },
        cleanupDuplicateHeaderRows(target = null) {
            // Conserver le calcul natif de DataTables pour éviter tout décalage
            // entre les largeurs d'en-tête et les cellules du tableau.
            return;
        },
        bindSearchHighlight(table, tableSelector, excludedIndexes = []) {
            if (!tableSelector || !table) {
                return;
            }

            const excluded = new Set(excludedIndexes);
            const $input = $(tableSelector + '_filter input');
            if (!$input.length) {
                return;
            }

            let searchTerm = '';

            const applyHighlight = () => {
                if (!searchTerm) {
                    table.rows().invalidate().draw(false);
                    return;
                }

                $(tableSelector.replace(/^#/, '#') + ' tbody tr').each((_, row) => {
                    $(row).find('td').each((index, cell) => {
                        if (excluded.has(index)) {
                            return;
                        }

                        const $cell = $(cell);
                        const currentHtml = $cell.html();
                        if (!currentHtml || currentHtml.includes('<mark')) {
                            return;
                        }

                        $cell.html(this.highlightSearchTerm(currentHtml, searchTerm));
                    });
                });
            };

            $input.off('keyup.ctrHighlight search.ctrHighlight input.ctrHighlight').on('keyup.ctrHighlight search.ctrHighlight input.ctrHighlight', function () {
                searchTerm = $(this).val();
                window.setTimeout(applyHighlight, 80);
            });

            $(table.table().node()).off('draw.dt.ctrHighlight').on('draw.dt.ctrHighlight', function () {
                if (searchTerm) {
                    window.requestAnimationFrame(applyHighlight);
                }
            });
        }
    };
}

(function () {
    function getNormalizedHeaderText(cell) {
        return (cell?.textContent || '').replace(/\s+/g, ' ').trim().toUpperCase();
    }

    function normalizeTargetHeaders(table) {
        if (!table || !table.tHead || !table.tHead.rows.length) {
            return;
        }

        const headerRow = table.tHead.rows[table.tHead.rows.length - 1];
        Array.from(headerRow.cells).forEach((cell) => {
            const text = getNormalizedHeaderText(cell);
            if (text === 'OBSERVATION' || text === 'OBSERVATIONS') {
                cell.textContent = 'OBN';
            }
        });
    }

    function hideTargetColumns(table) {
        if (!table || !table.tHead || !table.tHead.rows.length) {
            return;
        }

        normalizeTargetHeaders(table);

        const hiddenHeaders = ['ZDEF', 'QR', 'QRCODE', 'QR CODE', 'CODE QR'];
        const headerRow = table.tHead.rows[table.tHead.rows.length - 1];
        const hiddenIndexes = Array.from(headerRow.cells)
            .map((cell, index) => ({ index, text: getNormalizedHeaderText(cell) }))
            .filter((item) => hiddenHeaders.includes(item.text))
            .map((item) => item.index);

        if (!hiddenIndexes.length) {
            return;
        }

        const dataTableApi = window.jQuery && $.fn.dataTable && $.fn.dataTable.isDataTable(table)
            ? $(table).DataTable()
            : null;

        if (dataTableApi) {
            let visibilityChanged = false;

            hiddenIndexes.forEach((index) => {
                const column = dataTableApi.column(index);
                if (column && column.visible()) {
                    column.visible(false, false);
                    visibilityChanged = true;
                }
            });

            if (visibilityChanged) {
                dataTableApi.columns.adjust();
            }
        }

        Array.from(table.rows).forEach((row) => {
            hiddenIndexes.forEach((index) => {
                if (row.cells[index]) {
                    row.cells[index].style.display = 'none';
                }
            });
        });

        syncTableCellHeaders(table);
    }

    function getVisibleCells(row) {
        if (!row) {
            return [];
        }

        return Array.from(row.cells || []).filter((cell) => {
            if (!cell) {
                return false;
            }

            const computed = window.getComputedStyle(cell);
            return !cell.hidden
                && !cell.classList.contains('d-none')
                && computed.display !== 'none'
                && computed.visibility !== 'collapse';
        });
    }

    function syncTableCellHeaders(table) {
        if (!table || !table.tHead || !table.tHead.rows.length) {
            return;
        }

        const headerRow = table.tHead.rows[table.tHead.rows.length - 1];
        const visibleHeaders = getVisibleCells(headerRow).map((cell) => getNormalizedHeaderText(cell));

        Array.from(table.tBodies || []).forEach((tbody) => {
            Array.from(tbody.rows || []).forEach((row) => {
                const visibleCells = getVisibleCells(row);
                visibleCells.forEach((cell, index) => {
                    const headerText = visibleHeaders[index] || '';
                    if (headerText) {
                        cell.setAttribute('data-label', headerText);
                    } else {
                        cell.removeAttribute('data-label');
                    }
                });
            });
        });
    }

    function applyVisibleColumnRounding(table) {
        if (!table) {
            return;
        }

        table.querySelectorAll('thead th, thead td, tbody th, tbody td').forEach((cell) => {
            cell.classList.remove('ctr-first-visible', 'ctr-last-visible');
        });

        if (table.tHead) {
            Array.from(table.tHead.rows).forEach((row) => {
                const visibleCells = getVisibleCells(row);
                if (!visibleCells.length) {
                    return;
                }
                visibleCells[0].classList.add('ctr-first-visible');
                visibleCells[visibleCells.length - 1].classList.add('ctr-last-visible');
            });
        }

        Array.from(table.tBodies || []).forEach((tbody) => {
            Array.from(tbody.rows || []).forEach((row) => {
                const visibleCells = getVisibleCells(row);
                if (!visibleCells.length) {
                    return;
                }
                visibleCells[0].classList.add('ctr-first-visible');
                visibleCells[visibleCells.length - 1].classList.add('ctr-last-visible');
            });
        });
    }

    function removeTableScrollbars() {
        document.querySelectorAll('.table-responsive, .dataTables_scrollBody').forEach((element) => {
            element.style.overflowX = 'auto';
            element.style.overflowY = 'visible';
            element.style.maxHeight = 'none';

            if (element.classList.contains('dataTables_scrollBody')) {
                element.style.height = 'auto';
            }
        });
    }

    function applyHiddenColumnVisibility() {
        document.querySelectorAll('table').forEach((table) => {
            hideTargetColumns(table);
            syncTableCellHeaders(table);
            applyVisibleColumnRounding(table);
        });
    }

    function applyGlobalTableFixes(target = null) {
        applyHiddenColumnVisibility();
        removeTableScrollbars();
        if (window.CTRDataTableHelpers && typeof window.CTRDataTableHelpers.cleanupDuplicateHeaderRows === 'function') {
            window.CTRDataTableHelpers.cleanupDuplicateHeaderRows(target || document.querySelectorAll('table.dataTable'));
        }

        if (target) {
            const tables = target instanceof NodeList || Array.isArray(target) ? target : [target];
            Array.from(tables).forEach((table) => {
                syncTableCellHeaders(table);
                applyVisibleColumnRounding(table);
            });
            return;
        }

        document.querySelectorAll('table').forEach((table) => {
            syncTableCellHeaders(table);
            applyVisibleColumnRounding(table);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        applyGlobalTableFixes();
    });

    if (window.jQuery) {
        $(document).on('init.dt draw.dt column-sizing.dt responsive-resize.dt', function (event, settings) {
            if (settings && settings.nTable) {
                hideTargetColumns(settings.nTable);
                applyGlobalTableFixes(settings.nTable);
                return;
            }
            window.requestAnimationFrame(function () {
                applyGlobalTableFixes();
            });
        });
    }
})();
            </script>
            <!-- Scripts personnalisés -->
            <script
                src="<?= isset($appBasePath) ? htmlspecialchars($appBasePath) : '/ctr.net-fardc' ?>/assets/js/custom.js">
            </script>