pimcore.registerNS("pimcore.documentcopier");

pimcore.documentcopier = Class.create(pimcore.plugin.admin, {
    getClassName: function() {
        return "pimcore.documentcopier";
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
                    this.exportDialog(document);
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
                    this.importDialog(document);
                } else {
                    console.error("[DocumentCopier] Failed to open import dialog - document not found");
                }
            }.bind(this, record)
        });
    },

    exportDialog: function(document) {
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
                        this.exportHandler(document, exportForm)
                    }.bind(this, document, exportForm)
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

    exportHandler: function(document, form) {
        if (!form ||
            !form.getForm() ||
            !form.getForm().getValues() ||
            !form.getForm().getValues().depth
        ) {
            console.error("[DocumentCopier] Invalid form input - failed to obtain depth");
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

    importDialog: function(document) {
        console.log("import " + document.path);
        // TODO: add import form
        // TODO: handle import api
    }

});

var plugin = new pimcore.documentcopier();
