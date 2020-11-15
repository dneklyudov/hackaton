Vue.component('Modal', VueModal);

let vm = new Vue({
	
	el: '#app',

	data: {
		currentTab       : 1,
		uploadFile       : [],
		processedFiles   : [],
		completedFiles   : [],
		viewFile         : [],
		statFileProcessed: 0,
		statAddrProcessed: 0,
		intervalUpdate   : '',
		fileStatsText    : '',
		dialog           : {
			show     : false,
			title    : '',
			text     : '',
			yesAction: '',
			noAction: '',
		},
		modalViewFile : false,
		modalViewStats: false,
		pageLoader    : false,
		isMac         : false
	},

	methods: {
		
		// Выбор файла для загрузки
		fileSelect(event) {
			this.uploadFile = [];
			this.uploadFile.push(event.target.files[0])
		},

		// Бросок файла для загрузки
		fileDrop(event) {
			this.uploadFile = [];
			var droppedFiles = event.dataTransfer.files;
			if (event.dataTransfer.files.length > 1) {
				new Noty({
					text   : 'Максимум 1 файл',
					type   : 'error',
					timeout: 2000
				}).show();
			} else {
				([...droppedFiles]).forEach(file => {this.uploadFile.push(file)});
			}
		},

		// Загрузить файл
		fileLoad() {
			var formData = new FormData();
			formData.append('file', this.uploadFile[0]);
			fetch('/upload.php', {
				method: 'POST',
				body: formData
			})
			.then(result => result.json())
			.then(result => {
				console.log(result)
				if (result.error) {
					new Noty({
						text   : result.error,
						type   : 'error',
						timeout: 2000
					}).show();
				}
				if (result.message) {
					console.log(result)
					new Noty({
						text   : result.message,
						type   : 'success',
						timeout: 2000
					}).show();
					this.uploadFile = [];
					
					fetch('/data/get_billing_files.php')
					 	.then(response => response.json())
					 	.then(json => this.statFileProcessed = json.message);
					
				}
			});
		},
		
		// Поставить обработку файла на паузу
		fileCheckStop(file) {
			this.dialog = {
				title    : 'Приостановка обработки файла',
				text     : (!file.pause? 'Остановить обработку файла?' : 'Возобновить обработку файла?'),
				show     : true,
				noAction : () => {this.dialog.show = false},
				yesAction: () => {
					file.pause = !file.pause
					this.dialog.show = false;

					fetch('/data/file_stop.php', {
						method: 'POST',
						headers: {"Content-type": "application/x-www-form-urlencoded; charset=UTF-8"},
						body: 'id=' + file.id
					})
					.then(response => response.json())
					.then(result => {
						if (result.error) {
							new Noty({
								text   : result.error,
								type   : 'error',
								timeout: 2000
							}).show();
						}
						if (result.message) {
							console.log(result)
							new Noty({
								text   : result.message,
								type   : 'success',
								timeout: 2000
							}).show();
						}
					});
				}
			}

		},
		
		// Удалить файла из активных проверок
		deleteProcessedFile(file) {
			this.dialog = {
				title    : 'Удаление файла',
				text     : 'Файл находится в обработке, удалить его?',
				show     : true,
				noAction : () => {this.dialog.show = false},
				yesAction: () => {
					this.processedFiles = this.processedFiles.filter(el => {return el.id != file.id});
					this.dialog.show = false;

					fetch('/data/file_delete.php', {
						method: 'POST',
						headers: {"Content-type": "application/x-www-form-urlencoded; charset=UTF-8"},
						body: 'id=' + file.id
					})
					.then(response => response.json())
					.then(result => {
						if (result.error) {
							new Noty({
								text   : result.error,
								type   : 'error',
								timeout: 2000
							}).show();
						}
						if (result.message) {
							console.log(result)
							new Noty({
								text   : result.message,
								type   : 'success',
								timeout: 2000
							}).show();
						}
					});
				}
			}
		},

		// Удалить файла из завершенных проверок
		deleteCompletedFile(file) {
			this.dialog = {
				title    : 'Удаление файла',
				text     : 'Удалить файл из завершенных проверок?',
				show     : true,
				noAction : () => {this.dialog.show = false},
				yesAction: () => {
					this.completedFiles = this.completedFiles.filter(el => {return el.id != file.id});
					this.dialog.show = false;

					fetch('/data/file_delete.php', {
						method: 'POST',
						headers: {"Content-type": "application/x-www-form-urlencoded; charset=UTF-8"},
						body: 'id=' + file.id
					})
					.then(response => response.json())
					.then(result => {
						if (result.error) {
							new Noty({
								text   : result.error,
								type   : 'error',
								timeout: 2000
							}).show();
						}
						if (result.message) {
							console.log(result)
							new Noty({
								text   : result.message,
								type   : 'success',
								timeout: 2000
							}).show();
						}
					});					
				}
			}
		},

		// Просмотреть исправленный файл
		fileOpen(file) {
			this.pageLoader = true;
			fetch('/data/get_info.php', {
				method: 'POST',
				headers: {"Content-type": "application/x-www-form-urlencoded; charset=UTF-8"},
				body: 'id=' + file.path
			})
			.then(response => response.json())
			.then(json => {
				this.viewFile = json;
				this.modalViewFile = true;
				this.pageLoader = false;
			});
		},

		// Загрузить как CSV
		downloadCSV(file) {
			this.pageLoader = true;
			var vm = this;
			var xhr = new XMLHttpRequest();
			var data = 'id=' + file;

			xhr.open('POST', '/data/get_csv.php', true);
			xhr.responseType = 'blob';
			xhr.onload = function () {
				vm.pageLoader = false;
				if (this.status === 200) {
			        var blob = this.response;
			        var filename = "";
			        var disposition = xhr.getResponseHeader('Content-Disposition');
			        if (disposition && disposition.indexOf('attachment') !== -1) {
			            var filenameRegex = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/;
			            var matches = filenameRegex.exec(disposition);
			            if (matches != null && matches[1]) filename = matches[1].replace(/['"]/g, '');
			        }

			        if (typeof window.navigator.msSaveBlob !== 'undefined') {
			            // IE workaround for "HTML7007: One or more blob URLs were revoked by closing the blob for which they were created. These URLs will no longer resolve as the data backing the URL has been freed."
			            window.navigator.msSaveBlob(blob, filename);
			        } else {
			            var URL = window.URL || window.webkitURL;
						var downloadUrl = URL.createObjectURL(blob);
			            if (filename) {
			                // use HTML5 a[download] attribute to specify filename
			                var a = document.createElement("a");
			                // safari doesn't support this yet
			                if (typeof a.download === 'undefined') {
			                    window.location.href = downloadUrl;
			                } else {
			                    a.href = downloadUrl;
			                    a.download = filename;
								document.body.appendChild(a);
								a.click();
			                }
			            } else {
			                window.location.href = downloadUrl;
			            }

			            setTimeout(function () {
							URL.revokeObjectURL(downloadUrl); 
						}, 2000);
			        }
			    }
			};
			xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
			xhr.send(data);
	
		},

		// Загрузить как XLS
		downloadXLS(file) {

			var xhr = new XMLHttpRequest();
			var data = 'id=' + file;

			xhr.open('POST', '/data/get_xls.php', true);
			xhr.responseType = 'blob';
			xhr.onload = function () {
			    if (this.status === 200) {
			        var blob = this.response;
			        var filename = "";
			        var disposition = xhr.getResponseHeader('Content-Disposition');
			        if (disposition && disposition.indexOf('attachment') !== -1) {
			            var filenameRegex = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/;
			            var matches = filenameRegex.exec(disposition);
			            if (matches != null && matches[1]) filename = matches[1].replace(/['"]/g, '');
			        }

			        if (typeof window.navigator.msSaveBlob !== 'undefined') {
			            // IE workaround for "HTML7007: One or more blob URLs were revoked by closing the blob for which they were created. These URLs will no longer resolve as the data backing the URL has been freed."
			            window.navigator.msSaveBlob(blob, filename);
			        } else {
			            var URL = window.URL || window.webkitURL;
			            var downloadUrl = URL.createObjectURL(blob);

			            if (filename) {
			                // use HTML5 a[download] attribute to specify filename
			                var a = document.createElement("a");
			                // safari doesn't support this yet
			                if (typeof a.download === 'undefined') {
			                    window.location.href = downloadUrl;
			                } else {
			                    a.href = downloadUrl;
			                    a.download = filename;
			                    document.body.appendChild(a);
			                    a.click();
			                }
			            } else {
			                window.location.href = downloadUrl;
			            }

			            setTimeout(function () { URL.revokeObjectURL(downloadUrl); }, 2000); // cleanup
			        }
			    }
			};
			xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
			xhr.send(data);
		},

		// Выбор вкладки
		selectTab(num) {
			this.currentTab = num;
			clearInterval(this.intervalUpdate);
			
			if (num == 2) {
				fetch('/data/processed_files.php')
					.then(response => response.json())
					.then(json => this.processedFiles = json);

				this.intervalUpdate = setInterval(() => {
					fetch('/data/processed_files.php')
						.then(response => response.json())
						.then(json => this.processedFiles = json);
				}, 60000);
			}

			if (num == 1) {
				fetch('/data/completed_files.php')
					.then(response => response.json())
					.then(json => this.completedFiles = json);

				this.intervalUpdate = setInterval(() => {
					fetch('/data/completed_files.php')
						.then(response => response.json())
						.then(json => this.completedFiles = json);
				}, 60000);
			}
		},

		// Показать статистику по обработанным файлам
		showFileStats(file) {
			this.pageLoader = true;
			this.pageLoader = false;
			this.modalViewStats = true;
			fetch('/data/get_stat.php', {
			 	method: 'POST',
			 	headers: {"Content-type": "application/x-www-form-urlencoded; charset=UTF-8"},
			 	body: 'id=' + file.path
			 })
			.then(response => response.json())
			.then(json => {
				this.fileStatsText = json.message;
			 	this.modalViewStats = true;
			 	this.pageLoader = false;
			});
		},

		// Конвертация размера
		bytesToSize(bytes) {
			var sizes = ['Бт', 'Кб', 'Мб', 'Гб', 'Тб'];
			if (bytes == 0) return 'n/a';
			var i = parseInt(Math.floor(Math.log(bytes) / Math.log(1024)));
			if (i == 0) return bytes + ' ' + sizes[i];
			return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + sizes[i];
		},

		// Хоткеи
		onKeyDown(key) {
            if (key.key == 'Escape') this.dialog = false;
        }
	},

	mounted() {

		// json файлов В обработке
		fetch('/data/processed_files.php')
			.then(response => response.json())
			.then(json => this.processedFiles = json);
		
		// json Завершенных проверок файлов
		fetch('/data/completed_files.php')
			.then(response => response.json())
			.then(json => this.completedFiles = json);

		document.addEventListener('keydown', this.onKeyDown);

		fetch('/data/get_billing_files.php')
		 	.then(response => response.json())
		 	.then(json => this.statFileProcessed = json.message);


		// Обновление информации о кол-ве обработанных адресов
		setInterval(() => {
			fetch('/data/get_billing_files.php')
			 	.then(response => response.json())
			 	.then(json => this.statFileProcessed = json.message);
		}, 60000);


		fetch('/data/get_billing_addresses.php')
		 	.then(response => response.json())
		 	.then(json => this.statAddrProcessed = json.message);
		
		setInterval(() => {
			fetch('/data/get_billing_addresses.php')
			 	.then(response => response.json())
			 	.then(json => this.statAddrProcessed = json.message);
		}, 60000);

		if (navigator.userAgent.indexOf('Mac') > 0) this.isMac = true;

	}

});