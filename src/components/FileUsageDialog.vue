<template>
	<k-dialog
		ref="dialog"
		v-bind="$props"
		class="k-file-usage-dialog"
		@cancel="$emit('cancel')"
		@submit="$emit('submit')"
	>
		<h2 class="k-file-usage-title">{{ $t("medienbaecker.file-usage.title") }}</h2>
		<k-items v-if="found.length" :items="sorted" layout="list" />
		<k-empty v-else :icon="scanning ? 'loader' : 'page'">
			{{ $t(scanning ? "medienbaecker.file-usage.searching" : "medienbaecker.file-usage.empty") }}
		</k-empty>
		<footer class="k-file-usage-status">
			<k-progress v-if="scanning" :value="Math.round((done / queue.length) * 100)" />
			<p>{{ status }}</p>
		</footer>
	</k-dialog>
</template>

<script>
export default {
	extends: "k-text-dialog",
	props: {
		file: String,
		items: Array,
		queue: Array,
		total: Number,
	},
	data() {
		return {
			found: [...this.items],
			failed: [],
			fresh: [],
			done: 0,
			stopped: false,
		};
	},
	computed: {
		scanning() {
			return this.done < this.queue.length && !this.stopped;
		},
		sorted() {
			const order = [...this.items.map((item) => item.id), ...this.queue];

			return [...this.found]
				.sort((a, b) => order.indexOf(a.id) - order.indexOf(b.id))
				.map((item) => ({ ...item, class: this.fresh.includes(item.id) ? "k-file-usage-fresh" : null }));
		},
		status() {
			const count = this.found.length;

			if (this.scanning) {
				return this.plural("scanning", count, {
					done: this.total - this.queue.length + this.done,
					total: this.total,
				});
			}

			const found = this.plural("found", count);
			const result = this.failed.length
				? this.$t("medienbaecker.file-usage.failed", {
						count: this.failed.length,
						total: this.total,
						pages: this.failed.join(", "),
					})
				: this.$t("medienbaecker.file-usage.done", { total: this.total });

			return `${found} · ${result}`;
		},
	},
	mounted() {
		this.scan();
	},
	beforeDestroy() {
		this.stopped = true;
	},
	methods: {
		plural(key, count, data = {}) {
			return this.$t(`medienbaecker.file-usage.${key}.${count === 1 ? "one" : "other"}`, { count, ...data });
		},
		async scan() {
			const queue = [...this.queue];

			const worker = async () => {
				while (queue.length > 0 && !this.stopped) {
					const batch = queue.splice(0, 5);

					try {
						const result = await this.$panel.api.get(
							"file-usage/render",
							{ file: this.file, pages: batch.join(",") },
							{},
							true,
						);
						this.found.push(...result.items);
						this.failed.push(...result.failed);
						this.highlight(result.items);
					} catch {
						this.failed.push(...batch);
					} finally {
						this.done += batch.length;
					}
				}
			};

			await Promise.all(Array.from({ length: 4 }, worker));
		},
		highlight(items) {
			const ids = items.map((item) => item.id);
			this.fresh.push(...ids);
			setTimeout(() => (this.fresh = this.fresh.filter((id) => !ids.includes(id))), 1200);
		},
	},
};
</script>

<style>
.k-file-usage-title {
	margin-block-end: var(--spacing-4);
	font-size: var(--text-lg);
	font-weight: var(--font-bold);
	line-height: var(--leading-tight);
}

.k-file-usage-status {
	display: grid;
	gap: var(--spacing-2);
	margin-block-start: var(--spacing-3);
	color: var(--color-text-dimmed);
	font-size: var(--text-sm);
	line-height: var(--leading-normal);
}

.k-file-usage-fresh {
	animation: k-file-usage-fresh 1200ms ease-out;
}

@keyframes k-file-usage-fresh {
	from {
		background: light-dark(var(--color-blue-200), var(--color-blue-800));
	}
}
</style>
