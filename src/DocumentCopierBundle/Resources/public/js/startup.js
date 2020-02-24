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
            height: 150,
            width: 400,
            bodyPadding: 10,
            defaultType: "textfield",
            title: "Export document",
            floating: true,
            closable : true,
            items: [
                {
                    fieldLabel: "Dependency depth",
                    name: "depth",
                    xtype: "numberfield",
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
            !form.getForm().getValues() ||
            form.getForm().getValues().depth == null
        ) {
            console.error("[DocumentCopier] Invalid form input (failed to obtain field: depth)");
            return;
        }

        let depth = form.getForm().getValues().depth;
        form.disable();

        Ext.Ajax.request({
            url: '/admin/api/export-document',
            method: 'POST',

            jsonData: {
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
                if (response.status === 404) {
                    Ext.Msg.alert(
                        "[DocumentCopier] Configuration error",
                        "Endpoint does not exist. Did you configure routing for this bundle?",
                        Ext.emptyFn
                    );
                    form.hide();
                } else if (response.status === 400) {
                    console.log("[DocumentCopier] Invalid input: " + Ext.decode(response.responseText).message);
                    form.enable();
                } else {
                    console.error('[DocumentCopier] Export endpoint error ' + response.status);
                    form.enable();
                }
            }.bind(form)
        });
    },

    showImportDialog: function(document) {
        var importForm = new Ext.form.Panel({
            height: 150,
            width: 400,
            bodyPadding: 10,
            defaultType: "textfield",
            title: "Import document",
            floating: true,
            closable : true,
            items: [
                {
                    fieldLabel: "Dependency depth",
                    name: "depth",
                    xtype: "numberfield",
                    value: 1,
                    step: 1,
                    maxValue: 10,
                    minValue: 0,
                }
                // TODO: Add warning
            ],
            buttons: [
                {
                    text: "Import",
                    icon: "/bundles/pimcoreadmin/img/flat-color-icons/import.svg",
                    handler: function () {
                        this.handleImportForm(document, importForm);
                    }.bind(this, document)
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

    handleImportForm: function(document, form) {
        // TODO: handle import api
    },

});

let plugin = new pimcore.DocumentCopier();
