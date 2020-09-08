pimcore.registerNS("pimcore.DocumentCopier");

pimcore.DocumentCopier = Class.create(pimcore.plugin.admin, {
    getClassName: function() {
        return "pimcore.DocumentCopier";
    },

    initialize: function() {
        pimcore.plugin.broker.registerPlugin(this);
    },

    prepareDocumentTreeContextMenu: function(menu, tree, record) {
        menu.add({
            text: "Export",
            icon: "/bundles/pimcoreadmin/img/flat-color-icons/export.svg",
            handler: function (data) {
                let document = record.data;

                if (document) {
                    this.showExportDialog(document);
                } else {
                    console.error("[DocumentCopier] Failed to open export dialog - document not found");
                }
            }.bind(this, record)
        });

        menu.add({
            text: "Import",
            icon: "/bundles/pimcoreadmin/img/flat-color-icons/import.svg",
            handler: function (data) {
                let document = record.data;

                if (document) {
                    this.showImportDialog(document);
                } else {
                    console.error("[DocumentCopier] Failed to open import dialog - document not found");
                }
            }.bind(this, record)
        });
    },

    showExportDialog: function(document) {
        var exportForm = new Ext.form.Panel({
            width: 600,
            bodyPadding: 10,
            defaultType: "textfield",
            title: "Export document",
            floating: true,
            closable : true,
            html: "<p>Keep <em>dependency depth</em> small to avoid accidentally exporting too many documents</p>" +
                "<ul><li>If <strong>0</strong>, no dependencies (documents & assets) will be exported </li>" +
                "<li>If <strong>1</strong>, only direct dependencies will be exported (child documents, " +
                "as well as documents & assets referenced in the document)</li>" +
                "<li>If greater than <strong>1</strong>, " +
                "dependencies and their dependencies will be exported recursively</li></ul>",
            frame: true,
            items: [
                {
                    fieldLabel: "Dependency depth",
                    labelWidth: 150,
                    name: "depth",
                    xtype: "numberfield",
                    allowBlank: false,
                    value: 1,
                    step: 1,
                    maxValue: 10,
                    minValue: 0,
                }
            ],
            buttons: [
                {
                    text: "Export",
                    icon: "/bundles/pimcoreadmin/img/flat-color-icons/export.svg",
                    handler: function () {
                        this.handleExportForm(document, exportForm);
                    }.bind(this, document)
                },
                {
                    text: "Cancel",
                    icon: "/bundles/pimcoreadmin/img/flat-color-icons/cancel.svg",
                    handler: function() {
                        exportForm.hide();
                    }
                }
            ]
        });

        exportForm.show();
    },

    handleExportForm: function(document, form) {
        if (!form ||
            !form.getForm() ||
            !form.isValid() ||
            !form.getForm().getValues() ||
            form.getForm().getValues().depth == null
        ) {
            console.error("[DocumentCopier] Invalid form input");
            return;
        }

        let depth = form.getForm().getValues().depth;
        form.disable();

        Ext.Ajax.request({
            url: '/admin/api/export-document',
            method: 'POST',

            params: {
                'path': document.path,
                'depth': depth,
            },

            success: function(response, opts) {
                let obj = Ext.decode(response.responseText);

                if (obj.url) {
                    window.open(obj.url);
                    form.hide();
                } else {
                    console.error('[DocumentCopier] Unexpected response from export endpoint');
                    form.enable();
                }
            }.bind(form),

            failure: function(response, opts) {
                this.handleFailure(response, form);
            }.bind(this, form)
        });
    },

    showImportDialog: function(document) {
        var importForm = new Ext.form.Panel({
            title: 'Import document',
            width: 600,
            bodyPadding: 10,
            floating: true,
            closable : true,
            html: "<div style='text-align: center'>" +
                "<p style='color: #F93822;'><em>Warning:</em> Changes are applied immediately " +
                "and <strong>cannot be undone</strong>. <br>" +
                "Please review uploaded file before you continue.</p>" +
                "</div>" +
                "<p>Keep <em>dependency depth</em> small to avoid accidentally overwriting too many documents</p>" +
                "<ul><li>If <strong>0</strong>, no dependencies (documents & assets) will be imported </li>" +
                "<li>If <strong>1</strong>, only direct dependencies will be imported (child documents, " +
                "as well as documents & assets referenced in the document)</li>" +
                "<li>If greater than <strong>1</strong>, " +
                "dependencies and their dependencies will be imported recursively</li></ul>",
            frame: true,
            items: [
                {
                    xtype: 'filefield',
                    name: 'file',
                    fieldLabel: 'File to import',
                    labelWidth: 150,
                    accept: "zip,application/octet-stream,application/zip,application/x-zip,application/x-zip-compressed",
                    msgTarget: 'side',
                    allowBlank: false,
                    anchor: '100%',
                    buttonText: 'Select ZIP File...'
                },
                {
                    fieldLabel: "Dependency depth",
                    labelWidth: 150,
                    name: "depth",
                    xtype: "numberfield",
                    allowBlank: false,
                    value: 1,
                    step: 1,
                    maxValue: 10,
                    minValue: 0,
                },
                {
                    xtype: 'hiddenfield',
                    name: 'path',
                    value: document.path
                },
                {
                    xtype: 'hiddenfield',
                    name: 'csrfToken',
                    value: pimcore.settings.csrfToken
                }
            ],

            buttons: [
                {
                    text: 'Import',
                    icon: "/bundles/pimcoreadmin/img/flat-color-icons/import.svg",
                    handler: function() {
                        var form = this.up('form').getForm();
                        if (form.isValid()) {
                            form.submit({
                                url: '/admin/api/import-document',
                                waitMsg: 'Processing your import...',
                                success: function(fp, action) {
                                    Ext.Msg.alert(
                                        "[DocumentCopier] Import result",
                                        "Import successful",
                                        Ext.emptyFn
                                    );

                                    importForm.hide();

                                    let node = Ext.getCmp('pimcore_panel_tree_documents').getStore().getById(document.id);

                                    if (node && node.parentNode) {
                                        pimcore.elementservice.refreshNode(node.parentNode);
                                    }
                                },
                                failure: function(fp, action) {
                                    if (action.failureType === 'server') {
                                        Ext.Msg.alert(
                                            "[DocumentCopier] Import error",
                                            Ext.decode(action.response.responseText).message,
                                            Ext.emptyFn
                                        );
                                    } else {
                                        Ext.Msg.alert(
                                            "[DocumentCopier] Import error",
                                            'An error occured ( '  + action.failureType + ')',
                                            Ext.emptyFn
                                        );
                                    }

                                    importForm.hide();
                                }
                            });
                        } else {
                            console.error("[DocumentCopier] Invalid import form");
                        }
                    }
                },
                {
                    text: "Cancel",
                    icon: "/bundles/pimcoreadmin/img/flat-color-icons/cancel.svg",
                    handler: function() {
                        importForm.hide();
                    }
                }
            ]
        });

        importForm.show();
    },

    handleFailure: function(response, form) {
        if (response.status === 404) {
            Ext.Msg.alert(
                "[DocumentCopier] Configuration error",
                "Endpoint does not exist. Did you configure routing for this bundle?",
                Ext.emptyFn
            );
            form.hide();
        } else if (response.status === 400) {
            console.error("[DocumentCopier] Invalid input: " + Ext.decode(response.responseText).message);
            form.enable();
        } else {
            console.error('[DocumentCopier] Export endpoint error ' + response.status);
            form.enable();
        }
    }

});

let plugin = new pimcore.DocumentCopier();
