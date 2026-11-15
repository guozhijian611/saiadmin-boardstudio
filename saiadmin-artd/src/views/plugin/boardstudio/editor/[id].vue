<template>
  <div v-loading="loading" class="boardstudio-editor">
    <EditorToolbar
      v-model:zoom="zoom"
      :screen="screen"
      :dirty="dirty"
      :busy="busy || loading"
      :can-undo="canUndo"
      :can-redo="canRedo"
      :can-copy="canCopy"
      :can-paste="canPaste"
      :can-group="canGroup"
      :can-ungroup="canUngroup"
      :back="back"
      :export-layout="exportLayout"
      :import-layout="importLayout"
      :undo-layout="undoLayout"
      :redo-layout="redoLayout"
      :copy-selected="copySelected"
      :paste-copied="pasteCopied"
      :group-selected="groupSelected"
      :ungroup-selected="ungroupSelected"
      :save-layout="saveLayout"
      :open-draft-preview="openDraftPreview"
      :publish="publish"
      :open-presets="openPresets"
    />

    <div class="editor-main">
      <aside class="widget-panel">
        <div class="panel-title">组件</div>
        <ElInput
          v-model="widgetSearch"
          clearable
          placeholder="搜索组件"
          aria-label="搜索组件"
          class="mb-3"
        />
        <button
          class="widget-button widget-button--presets"
          type="button"
          @click="openPresets('component')"
        >
          <ArtSvgIcon icon="ri:store-2-line" />
          <span>组件预设</span>
        </button>
        <button
          v-for="widget in filteredWidgets"
          :key="widget.type"
          class="widget-button"
          type="button"
          @click="addWidget(widget.type)"
        >
          <ArtSvgIcon :icon="widget.icon" />
          <span>{{ widget.name }}</span>
        </button>
        <div class="panel-title panel-title--layers">图层</div>
        <div class="layer-list">
          <ElText v-if="!layerItems.length" type="info" size="small">暂无组件</ElText>
          <div
            v-for="component in layerItems"
            :key="component.id"
            class="layer-item"
            :class="{ 'is-active': selectedIds.includes(component.id) }"
            @click="selectLayer(component.id, $event)"
          >
            <span class="layer-item__name">{{ component.title || componentName(component) }}</span>
            <span class="layer-item__type">{{ componentName(component) }}</span>
            <span v-if="componentGroupId(component)" class="layer-item__tag">组</span>
            <span class="layer-item__actions">
              <button
                type="button"
                title="上移"
                :disabled="isLayerTop(component)"
                @click.stop="moveLayer(component, 'up')"
              >
                ↑
              </button>
              <button
                type="button"
                title="下移"
                :disabled="isLayerBottom(component)"
                @click.stop="moveLayer(component, 'down')"
              >
                ↓
              </button>
              <button type="button" title="置顶" @click.stop="sendLayerToTop(component)">顶</button>
              <button type="button" title="置底" @click.stop="sendLayerToBottom(component)">
                底
              </button>
            </span>
          </div>
        </div>
      </aside>

      <main ref="canvasShellRef" class="canvas-shell">
        <div
          class="canvas-frame"
          :style="{
            width: scaledCanvasSize.width + 'px',
            height: scaledCanvasSize.height + 'px'
          }"
        >
          <div
            class="canvas"
            :style="{
              ...canvasStyle,
              width: layout.canvas.width + 'px',
              height: layout.canvas.height + 'px',
              transform: `scale(${effectiveZoom})`
            }"
            @mousedown.self="clearSelection"
          >
            <DraggableItem
              v-for="component in layout.components"
              :key="component.id"
              :component="component"
              :rows="componentRows(component)"
              :error="componentError(component)"
              :selected="selectedIds.includes(component.id)"
              :resizable="selectedComponents.length <= 1"
              :zoom="effectiveZoom"
              @select="selectComponent"
              @resize-start="startComponentResize"
              @update="updateComponent"
            />
            <Vue3DraggableResizable
              v-if="selectionBox"
              :x="selectionBox.x"
              :y="selectionBox.y"
              :w="selectionBox.w"
              :h="selectionBox.h"
              :z="10000"
              :active="true"
              :parent="true"
              :draggable="true"
              :resizable="true"
              :min-w="selectionResizeMin.w"
              :min-h="selectionResizeMin.h"
              class-name="boardstudio-selection-box"
              class-name-active="boardstudio-selection-box-active"
              @drag-start="startSelectionDrag"
              @resize-start="startSelectionResize"
              @dragging="dragSelectionBox"
              @drag-end="finishSelectionDrag"
              @resizing="resizeSelectionBox"
              @resize-end="finishSelectionResize"
            />
          </div>
        </div>
      </main>

      <aside class="property-panel">
        <template v-if="selectedComponent">
          <div class="panel-title">属性</div>
          <ElForm label-width="84px">
            <ElFormItem label="标题">
              <ElInput v-model="selectedComponent.title" />
            </ElFormItem>
            <ElFormItem label="类型">
              <ElSelect v-model="selectedComponent.type">
                <ElOption
                  v-for="widget in filteredWidgets"
                  :key="widget.type"
                  :label="widget.name"
                  :value="widget.type"
                />
              </ElSelect>
            </ElFormItem>
            <ElFormItem v-if="requiresDataset" label="查询模板">
              <div class="template-bind-row">
                <ElSelect
                  v-model="selectedComponent.dataset.queryTemplateId"
                  clearable
                  filterable
                  @change="onTemplateChange"
                >
                  <ElOption
                    v-for="item in templateOptions"
                    :key="item.id"
                    :label="item.name"
                    :value="item.id"
                  />
                </ElSelect>
                <ElButton
                  v-permission="'boardstudio:query_template:preview'"
                  :disabled="!selectedComponent.dataset.queryTemplateId"
                  :loading="selectedPreviewLoading"
                  @click="previewSelectedData"
                >
                  预览
                </ElButton>
              </div>
            </ElFormItem>
            <ElFormItem v-if="requiresDataset" label="刷新秒">
              <ElInputNumber v-model="selectedComponent.dataset.refresh" :min="10" :max="3600" />
            </ElFormItem>
            <ElFormItem v-if="requiresDataset" label="查询参数">
              <ElInput
                v-model="datasetParamsText"
                type="textarea"
                :rows="4"
                placeholder='{"tenant":"b8","range":"7d"}'
                @blur="applySelectedDatasetParams"
              />
            </ElFormItem>
            <ElFormItem v-if="supportsFieldMapping" label="类目字段">
              <ElSelect
                v-model="selectedComponent.dataset.mapping!.labelField"
                clearable
                filterable
                placeholder="自动识别"
                :disabled="!fieldOptions.length"
              >
                <ElOption
                  v-for="field in fieldOptions"
                  :key="field"
                  :label="field"
                  :value="field"
                />
              </ElSelect>
            </ElFormItem>
            <ElFormItem v-if="supportsFieldMapping" label="数值字段">
              <ElSelect
                v-model="selectedComponent.dataset.mapping!.valueField"
                clearable
                filterable
                placeholder="自动识别"
                :disabled="!fieldOptions.length"
              >
                <ElOption
                  v-for="field in numericFieldOptions"
                  :key="field"
                  :label="field"
                  :value="field"
                />
              </ElSelect>
            </ElFormItem>
            <ElFormItem v-if="selectedComponent.type === 'data-table'" label="表格字段">
              <ElSelect
                v-model="selectedComponent.dataset.mapping!.tableFields"
                multiple
                collapse-tags
                collapse-tags-tooltip
                filterable
                placeholder="默认前 8 列"
                :disabled="!fieldOptions.length"
                @change="syncSelectedTableColumns"
              >
                <ElOption
                  v-for="field in fieldOptions"
                  :key="field"
                  :label="field"
                  :value="field"
                />
              </ElSelect>
            </ElFormItem>
            <ElFormItem v-if="selectedComponent.type === 'data-table'" label="列配置">
              <div class="table-column-config">
                <ElText v-if="!selectedTableColumns.length" type="info" size="small">
                  选择表格字段后可配置列别名、宽度和对齐
                </ElText>
                <div
                  v-for="column in selectedTableColumns"
                  :key="column.field"
                  class="table-column-config__item"
                >
                  <ElText class="table-column-config__field" truncated>
                    {{ column.field }}
                  </ElText>
                  <ElInput v-model="column.label" clearable placeholder="显示名，默认字段名" />
                  <div class="table-column-config__controls">
                    <ElInputNumber
                      v-model="column.width"
                      class="table-column-config__width"
                      :min="60"
                      :max="600"
                      :step="10"
                      :controls="false"
                      placeholder="宽度"
                    />
                    <ElSelect
                      v-model="column.align"
                      class="table-column-config__align"
                      placeholder="对齐"
                    >
                      <ElOption
                        v-for="item in tableAlignOptions"
                        :key="item.value"
                        :label="item.label"
                        :value="item.value"
                      />
                    </ElSelect>
                  </div>
                </div>
              </div>
            </ElFormItem>
            <ElFormItem v-if="requiresDataset" label="数据预览">
              <ElText v-if="selectedPreviewError" type="danger" truncated>
                {{ selectedPreviewError }}
              </ElText>
              <ElText v-else-if="selectedComponent.dataset.queryTemplateId" type="success">
                已加载 {{ selectedPreviewRows.length }} 行
              </ElText>
              <ElText v-else type="info">未绑定查询模板，使用示例数据</ElText>
            </ElFormItem>
            <ElFormItem label="位置">
              <ElSpace wrap>
                <ElInputNumber
                  v-model="selectedComponent.rect.x"
                  :min="0"
                  :max="layout.canvas.width"
                />
                <ElInputNumber
                  v-model="selectedComponent.rect.y"
                  :min="0"
                  :max="layout.canvas.height"
                />
              </ElSpace>
            </ElFormItem>
            <ElFormItem label="尺寸">
              <ElSpace wrap>
                <ElInputNumber
                  v-model="selectedComponent.rect.w"
                  :min="120"
                  :max="layout.canvas.width"
                />
                <ElInputNumber
                  v-model="selectedComponent.rect.h"
                  :min="80"
                  :max="layout.canvas.height"
                />
              </ElSpace>
            </ElFormItem>
            <ElFormItem label="层级">
              <ElInputNumber v-model="selectedComponent.rect.z" :min="1" :max="999" />
            </ElFormItem>
            <template v-if="selectedComponent.type === 'stat-number'">
              <ElFormItem label="前缀">
                <ElInput v-model="selectedComponent.option!.prefix" placeholder="例如 ¥" />
              </ElFormItem>
              <ElFormItem label="小数位">
                <ElInputNumber
                  v-model="selectedComponent.option!.decimals"
                  :min="0"
                  :max="6"
                  :step="1"
                  step-strictly
                />
              </ElFormItem>
              <ElFormItem label="单位">
                <ElInput v-model="selectedComponent.option!.unit" placeholder="例如 单、元、%" />
              </ElFormItem>
            </template>
            <template v-if="selectedComponent.type === 'data-table'">
              <ElFormItem label="最大行数">
                <ElInputNumber
                  v-model="selectedComponent.option!.maxRows"
                  :min="0"
                  :max="1000"
                  :step="1"
                  step-strictly
                />
              </ElFormItem>
              <ElFormItem label="序号列">
                <ElSwitch v-model="selectedComponent.option!.showIndex" />
              </ElFormItem>
              <ElFormItem label="斑马纹">
                <ElSwitch v-model="selectedComponent.option!.rowStripe" />
              </ElFormItem>
            </template>
            <template v-if="selectedComponent.type === 'art-dual-bar-compare-chart'">
              <ElFormItem label="正向字段">
                <ElSelect
                  v-model="selectedComponent.option!.positiveField"
                  clearable
                  filterable
                  placeholder="自动识别"
                  :disabled="!numericFieldOptions.length"
                >
                  <ElOption
                    v-for="field in numericFieldOptions"
                    :key="field"
                    :label="field"
                    :value="field"
                  />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="负向字段">
                <ElSelect
                  v-model="selectedComponent.option!.negativeField"
                  clearable
                  filterable
                  placeholder="自动识别"
                  :disabled="!numericFieldOptions.length"
                >
                  <ElOption
                    v-for="field in numericFieldOptions"
                    :key="field"
                    :label="field"
                    :value="field"
                  />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="正向名称">
                <ElInput v-model="selectedComponent.option!.positiveName" />
              </ElFormItem>
              <ElFormItem label="负向名称">
                <ElInput v-model="selectedComponent.option!.negativeName" />
              </ElFormItem>
              <ElFormItem label="显示图例">
                <ElSwitch v-model="selectedComponent.option!.showLegend" />
              </ElFormItem>
              <ElFormItem label="数值标签">
                <ElSwitch v-model="selectedComponent.option!.showDataLabel" />
              </ElFormItem>
            </template>
            <template v-if="selectedComponent.type === 'art-k-line-chart'">
              <ElFormItem label="时间字段">
                <ElSelect
                  v-model="selectedComponent.option!.timeField"
                  clearable
                  filterable
                  placeholder="自动识别"
                  :disabled="!fieldOptions.length"
                >
                  <ElOption
                    v-for="field in fieldOptions"
                    :key="field"
                    :label="field"
                    :value="field"
                  />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="开盘字段">
                <ElSelect
                  v-model="selectedComponent.option!.openField"
                  clearable
                  filterable
                  placeholder="自动识别"
                  :disabled="!numericFieldOptions.length"
                >
                  <ElOption
                    v-for="field in numericFieldOptions"
                    :key="field"
                    :label="field"
                    :value="field"
                  />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="收盘字段">
                <ElSelect
                  v-model="selectedComponent.option!.closeField"
                  clearable
                  filterable
                  placeholder="自动识别"
                  :disabled="!numericFieldOptions.length"
                >
                  <ElOption
                    v-for="field in numericFieldOptions"
                    :key="field"
                    :label="field"
                    :value="field"
                  />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="最高字段">
                <ElSelect
                  v-model="selectedComponent.option!.highField"
                  clearable
                  filterable
                  placeholder="自动识别"
                  :disabled="!numericFieldOptions.length"
                >
                  <ElOption
                    v-for="field in numericFieldOptions"
                    :key="field"
                    :label="field"
                    :value="field"
                  />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="最低字段">
                <ElSelect
                  v-model="selectedComponent.option!.lowField"
                  clearable
                  filterable
                  placeholder="自动识别"
                  :disabled="!numericFieldOptions.length"
                >
                  <ElOption
                    v-for="field in numericFieldOptions"
                    :key="field"
                    :label="field"
                    :value="field"
                  />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="缩放条">
                <ElSwitch v-model="selectedComponent.option!.showDataZoom" />
              </ElFormItem>
              <ElFormItem v-if="selectedComponent.option!.showDataZoom" label="缩放范围">
                <ElSpace wrap>
                  <ElInputNumber
                    v-model="selectedComponent.option!.dataZoomStart"
                    :min="0"
                    :max="100"
                    :step="1"
                    step-strictly
                  />
                  <ElInputNumber
                    v-model="selectedComponent.option!.dataZoomEnd"
                    :min="0"
                    :max="100"
                    :step="1"
                    step-strictly
                  />
                </ElSpace>
              </ElFormItem>
            </template>
            <template v-if="selectedComponent.type === 'art-gauge-chart'">
              <ElFormItem label="显示名称">
                <ElInput v-model="selectedComponent.option!.name" placeholder="默认使用标题" />
              </ElFormItem>
              <ElFormItem label="最小值">
                <ElInputNumber
                  v-model="selectedComponent.option!.min"
                  :min="-999999999"
                  :max="999999999"
                  :step="1"
                />
              </ElFormItem>
              <ElFormItem label="最大值">
                <ElInputNumber
                  v-model="selectedComponent.option!.max"
                  :min="-999999999"
                  :max="999999999"
                  :step="1"
                />
              </ElFormItem>
              <ElFormItem label="单位">
                <ElInput v-model="selectedComponent.option!.unit" maxlength="12" />
              </ElFormItem>
              <ElFormItem label="小数位">
                <ElInputNumber
                  v-model="selectedComponent.option!.decimals"
                  :min="0"
                  :max="6"
                  :step="1"
                  step-strictly
                />
              </ElFormItem>
              <ElFormItem label="指针">
                <ElSwitch v-model="selectedComponent.option!.showPointer" />
              </ElFormItem>
              <ElFormItem label="进度环">
                <ElSwitch v-model="selectedComponent.option!.showProgress" />
              </ElFormItem>
            </template>
            <template v-if="selectedComponent.type === 'art-funnel-chart'">
              <ElFormItem label="显示标签">
                <ElSwitch v-model="selectedComponent.option!.showLabel" />
              </ElFormItem>
              <ElFormItem label="排序">
                <ElSelect v-model="selectedComponent.option!.sort">
                  <ElOption label="从大到小" value="descending" />
                  <ElOption label="从小到大" value="ascending" />
                  <ElOption label="不排序" value="none" />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="显示图例">
                <ElSwitch v-model="selectedComponent.option!.showLegend" />
              </ElFormItem>
              <ElFormItem v-if="selectedComponent.option!.showLegend" label="图例位置">
                <ElSelect v-model="selectedComponent.option!.legendPosition">
                  <ElOption label="右侧" value="right" />
                  <ElOption label="左侧" value="left" />
                  <ElOption label="顶部" value="top" />
                  <ElOption label="底部" value="bottom" />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="块间距">
                <ElInputNumber
                  v-model="selectedComponent.option!.gap"
                  :min="0"
                  :max="20"
                  :step="1"
                  step-strictly
                />
              </ElFormItem>
              <ElFormItem label="最小宽度">
                <ElInput v-model="selectedComponent.option!.minSize" placeholder="例如 20%" />
              </ElFormItem>
              <ElFormItem label="最大宽度">
                <ElInput v-model="selectedComponent.option!.maxSize" placeholder="例如 82%" />
              </ElFormItem>
            </template>
            <template v-if="selectedComponent.type === 'art-heatmap-chart'">
              <ElFormItem label="X字段">
                <ElSelect
                  v-model="selectedComponent.option!.xField"
                  clearable
                  filterable
                  placeholder="自动识别"
                  :disabled="!fieldOptions.length"
                >
                  <ElOption
                    v-for="field in fieldOptions"
                    :key="field"
                    :label="field"
                    :value="field"
                  />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="Y字段">
                <ElSelect
                  v-model="selectedComponent.option!.yField"
                  clearable
                  filterable
                  placeholder="自动识别"
                  :disabled="!fieldOptions.length"
                >
                  <ElOption
                    v-for="field in fieldOptions"
                    :key="field"
                    :label="field"
                    :value="field"
                  />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="显示数值">
                <ElSwitch v-model="selectedComponent.option!.showLabel" />
              </ElFormItem>
              <ElFormItem label="颜色标尺">
                <ElSwitch v-model="selectedComponent.option!.showVisualMap" />
              </ElFormItem>
              <ElFormItem label="坐标标签">
                <ElSwitch v-model="selectedComponent.option!.showAxisLabel" />
              </ElFormItem>
              <ElFormItem label="坐标轴线">
                <ElSwitch v-model="selectedComponent.option!.showAxisLine" />
              </ElFormItem>
              <ElFormItem label="分割线">
                <ElSwitch v-model="selectedComponent.option!.showSplitLine" />
              </ElFormItem>
              <ElFormItem label="取值范围">
                <ElSpace wrap>
                  <ElInputNumber
                    v-model="selectedComponent.option!.min"
                    :min="-999999999"
                    :max="999999999"
                    :step="1"
                  />
                  <ElInputNumber
                    v-model="selectedComponent.option!.max"
                    :min="-999999999"
                    :max="999999999"
                    :step="1"
                    placeholder="自动"
                  />
                </ElSpace>
              </ElFormItem>
            </template>
            <template v-if="selectedComponent.type === 'event-timeline'">
              <ElFormItem label="时间字段">
                <ElSelect
                  v-model="selectedComponent.option!.timeField"
                  clearable
                  filterable
                  placeholder="自动识别"
                  :disabled="!fieldOptions.length"
                >
                  <ElOption
                    v-for="field in fieldOptions"
                    :key="field"
                    :label="field"
                    :value="field"
                  />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="标题字段">
                <ElSelect
                  v-model="selectedComponent.option!.titleField"
                  clearable
                  filterable
                  placeholder="自动识别"
                  :disabled="!fieldOptions.length"
                >
                  <ElOption
                    v-for="field in fieldOptions"
                    :key="field"
                    :label="field"
                    :value="field"
                  />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="内容字段">
                <ElSelect
                  v-model="selectedComponent.option!.contentField"
                  clearable
                  filterable
                  placeholder="自动识别"
                  :disabled="!fieldOptions.length"
                >
                  <ElOption
                    v-for="field in fieldOptions"
                    :key="field"
                    :label="field"
                    :value="field"
                  />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="状态字段">
                <ElSelect
                  v-model="selectedComponent.option!.statusField"
                  clearable
                  filterable
                  placeholder="自动识别"
                  :disabled="!fieldOptions.length"
                >
                  <ElOption
                    v-for="field in fieldOptions"
                    :key="field"
                    :label="field"
                    :value="field"
                  />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="排序">
                <ElSelect v-model="selectedComponent.option!.sortOrder">
                  <ElOption label="时间倒序" value="desc" />
                  <ElOption label="时间正序" value="asc" />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="最大条数">
                <ElInputNumber
                  v-model="selectedComponent.option!.maxRows"
                  :min="0"
                  :max="50"
                  :step="1"
                  step-strictly
                />
              </ElFormItem>
              <ElFormItem label="显示时间">
                <ElSwitch v-model="selectedComponent.option!.showTime" />
              </ElFormItem>
              <ElFormItem label="显示内容">
                <ElSwitch v-model="selectedComponent.option!.showContent" />
              </ElFormItem>
              <ElFormItem label="强调色">
                <ElColorPicker v-model="selectedComponent.option!.accent" />
              </ElFormItem>
            </template>
            <template v-if="selectedComponent.type === 'alarm-list'">
              <ElFormItem label="时间字段">
                <ElSelect
                  v-model="selectedComponent.option!.timeField"
                  clearable
                  filterable
                  placeholder="自动识别"
                  :disabled="!fieldOptions.length"
                >
                  <ElOption
                    v-for="field in fieldOptions"
                    :key="field"
                    :label="field"
                    :value="field"
                  />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="标题字段">
                <ElSelect
                  v-model="selectedComponent.option!.titleField"
                  clearable
                  filterable
                  placeholder="自动识别"
                  :disabled="!fieldOptions.length"
                >
                  <ElOption
                    v-for="field in fieldOptions"
                    :key="field"
                    :label="field"
                    :value="field"
                  />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="内容字段">
                <ElSelect
                  v-model="selectedComponent.option!.contentField"
                  clearable
                  filterable
                  placeholder="自动识别"
                  :disabled="!fieldOptions.length"
                >
                  <ElOption
                    v-for="field in fieldOptions"
                    :key="field"
                    :label="field"
                    :value="field"
                  />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="级别字段">
                <ElSelect
                  v-model="selectedComponent.option!.levelField"
                  clearable
                  filterable
                  placeholder="自动识别"
                  :disabled="!fieldOptions.length"
                >
                  <ElOption
                    v-for="field in fieldOptions"
                    :key="field"
                    :label="field"
                    :value="field"
                  />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="排序">
                <ElSelect v-model="selectedComponent.option!.sortOrder">
                  <ElOption label="时间倒序" value="desc" />
                  <ElOption label="时间正序" value="asc" />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="最大条数">
                <ElInputNumber
                  v-model="selectedComponent.option!.maxRows"
                  :min="0"
                  :max="50"
                  :step="1"
                  step-strictly
                />
              </ElFormItem>
              <ElFormItem label="显示时间">
                <ElSwitch v-model="selectedComponent.option!.showTime" />
              </ElFormItem>
              <ElFormItem label="显示内容">
                <ElSwitch v-model="selectedComponent.option!.showContent" />
              </ElFormItem>
              <ElFormItem label="强调色">
                <ElColorPicker v-model="selectedComponent.option!.accent" />
              </ElFormItem>
            </template>
            <template v-if="selectedComponent.type === 'progress-rank'">
              <ElFormItem label="名称字段">
                <ElSelect
                  v-model="selectedComponent.option!.labelField"
                  clearable
                  filterable
                  placeholder="自动识别"
                  :disabled="!fieldOptions.length"
                >
                  <ElOption
                    v-for="field in fieldOptions"
                    :key="field"
                    :label="field"
                    :value="field"
                  />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="数值字段">
                <ElSelect
                  v-model="selectedComponent.option!.valueField"
                  clearable
                  filterable
                  placeholder="自动识别"
                  :disabled="!fieldOptions.length"
                >
                  <ElOption
                    v-for="field in fieldOptions"
                    :key="field"
                    :label="field"
                    :value="field"
                  />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="目标字段">
                <ElSelect
                  v-model="selectedComponent.option!.targetField"
                  clearable
                  filterable
                  placeholder="可选"
                  :disabled="!fieldOptions.length"
                >
                  <ElOption
                    v-for="field in fieldOptions"
                    :key="field"
                    :label="field"
                    :value="field"
                  />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="状态字段">
                <ElSelect
                  v-model="selectedComponent.option!.statusField"
                  clearable
                  filterable
                  placeholder="可选"
                  :disabled="!fieldOptions.length"
                >
                  <ElOption
                    v-for="field in fieldOptions"
                    :key="field"
                    :label="field"
                    :value="field"
                  />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="排序">
                <ElSelect v-model="selectedComponent.option!.sortOrder">
                  <ElOption label="进度倒序" value="desc" />
                  <ElOption label="进度正序" value="asc" />
                  <ElOption label="原始顺序" value="none" />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="最大条数">
                <ElInputNumber
                  v-model="selectedComponent.option!.maxRows"
                  :min="0"
                  :max="50"
                  :step="1"
                  step-strictly
                />
              </ElFormItem>
              <ElFormItem label="单位">
                <ElInput v-model="selectedComponent.option!.unit" placeholder="例如 %、单、件" />
              </ElFormItem>
              <ElFormItem label="小数位">
                <ElInputNumber
                  v-model="selectedComponent.option!.decimals"
                  :min="0"
                  :max="6"
                  :step="1"
                  step-strictly
                />
              </ElFormItem>
              <ElFormItem label="显示排名">
                <ElSwitch v-model="selectedComponent.option!.showRank" />
              </ElFormItem>
              <ElFormItem label="显示数值">
                <ElSwitch v-model="selectedComponent.option!.showValue" />
              </ElFormItem>
              <ElFormItem label="强调色">
                <ElColorPicker v-model="selectedComponent.option!.accent" />
              </ElFormItem>
            </template>
            <template v-if="selectedComponent.type === 'status-matrix'">
              <ElFormItem label="名称字段">
                <ElSelect
                  v-model="selectedComponent.option!.labelField"
                  clearable
                  filterable
                  placeholder="自动识别"
                  :disabled="!fieldOptions.length"
                >
                  <ElOption
                    v-for="field in fieldOptions"
                    :key="field"
                    :label="field"
                    :value="field"
                  />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="状态字段">
                <ElSelect
                  v-model="selectedComponent.option!.statusField"
                  clearable
                  filterable
                  placeholder="自动识别"
                  :disabled="!fieldOptions.length"
                >
                  <ElOption
                    v-for="field in fieldOptions"
                    :key="field"
                    :label="field"
                    :value="field"
                  />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="数值字段">
                <ElSelect
                  v-model="selectedComponent.option!.valueField"
                  clearable
                  filterable
                  placeholder="可选"
                  :disabled="!fieldOptions.length"
                >
                  <ElOption
                    v-for="field in fieldOptions"
                    :key="field"
                    :label="field"
                    :value="field"
                  />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="分组字段">
                <ElSelect
                  v-model="selectedComponent.option!.groupField"
                  clearable
                  filterable
                  placeholder="可选"
                  :disabled="!fieldOptions.length"
                >
                  <ElOption
                    v-for="field in fieldOptions"
                    :key="field"
                    :label="field"
                    :value="field"
                  />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="最大条数">
                <ElInputNumber
                  v-model="selectedComponent.option!.maxRows"
                  :min="0"
                  :max="50"
                  :step="1"
                  step-strictly
                />
              </ElFormItem>
              <ElFormItem label="列数">
                <ElInputNumber
                  v-model="selectedComponent.option!.columns"
                  :min="1"
                  :max="6"
                  :step="1"
                  step-strictly
                />
              </ElFormItem>
              <ElFormItem label="单位">
                <ElInput v-model="selectedComponent.option!.unit" placeholder="例如 %、单、ms" />
              </ElFormItem>
              <ElFormItem label="小数位">
                <ElInputNumber
                  v-model="selectedComponent.option!.decimals"
                  :min="0"
                  :max="6"
                  :step="1"
                  step-strictly
                />
              </ElFormItem>
              <ElFormItem label="显示状态">
                <ElSwitch v-model="selectedComponent.option!.showStatus" />
              </ElFormItem>
              <ElFormItem label="显示数值">
                <ElSwitch v-model="selectedComponent.option!.showValue" />
              </ElFormItem>
              <ElFormItem label="强调色">
                <ElColorPicker v-model="selectedComponent.option!.accent" />
              </ElFormItem>
            </template>
            <template v-if="selectedComponent.type === 'image-carousel'">
              <ElFormItem label="图片字段">
                <ElSelect
                  v-model="selectedComponent.option!.imageField"
                  clearable
                  filterable
                  placeholder="自动识别"
                  :disabled="!fieldOptions.length"
                >
                  <ElOption
                    v-for="field in fieldOptions"
                    :key="field"
                    :label="field"
                    :value="field"
                  />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="标题字段">
                <ElSelect
                  v-model="selectedComponent.option!.titleField"
                  clearable
                  filterable
                  placeholder="自动识别"
                  :disabled="!fieldOptions.length"
                >
                  <ElOption
                    v-for="field in fieldOptions"
                    :key="field"
                    :label="field"
                    :value="field"
                  />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="切换毫秒">
                <ElInputNumber
                  v-model="selectedComponent.option!.interval"
                  :min="1000"
                  :max="60000"
                  :step="500"
                  step-strictly
                />
              </ElFormItem>
              <ElFormItem label="图片适配">
                <ElSelect v-model="selectedComponent.option!.imageFit">
                  <ElOption
                    v-for="item in imageFitOptions"
                    :key="item.value"
                    :label="item.label"
                    :value="item.value"
                  />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="指示点">
                <ElSwitch v-model="selectedComponent.option!.showDots" />
              </ElFormItem>
            </template>
            <template v-if="selectedComponent.type === 'geo-point-map'">
              <ElFormItem label="经度字段">
                <ElSelect
                  v-model="selectedComponent.option!.lngField"
                  clearable
                  filterable
                  placeholder="自动识别"
                  :disabled="!fieldOptions.length"
                >
                  <ElOption
                    v-for="field in fieldOptions"
                    :key="field"
                    :label="field"
                    :value="field"
                  />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="纬度字段">
                <ElSelect
                  v-model="selectedComponent.option!.latField"
                  clearable
                  filterable
                  placeholder="自动识别"
                  :disabled="!fieldOptions.length"
                >
                  <ElOption
                    v-for="field in fieldOptions"
                    :key="field"
                    :label="field"
                    :value="field"
                  />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="名称字段">
                <ElSelect
                  v-model="selectedComponent.option!.nameField"
                  clearable
                  filterable
                  placeholder="自动识别"
                  :disabled="!fieldOptions.length"
                >
                  <ElOption
                    v-for="field in fieldOptions"
                    :key="field"
                    :label="field"
                    :value="field"
                  />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="数值字段">
                <ElSelect
                  v-model="selectedComponent.option!.valueField"
                  clearable
                  filterable
                  placeholder="自动识别"
                  :disabled="!fieldOptions.length"
                >
                  <ElOption
                    v-for="field in fieldOptions"
                    :key="field"
                    :label="field"
                    :value="field"
                  />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="地图范围">
                <ElSelect v-model="selectedComponent.option!.region">
                  <ElOption
                    v-for="item in mapRegionOptions"
                    :key="item.value"
                    :label="item.label"
                    :value="item.value"
                  />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="点大小">
                <ElInputNumber
                  v-model="selectedComponent.option!.pointSize"
                  :min="6"
                  :max="28"
                  :step="1"
                  step-strictly
                />
              </ElFormItem>
              <ElFormItem label="点标签">
                <ElSwitch v-model="selectedComponent.option!.showLabel" />
              </ElFormItem>
            </template>
            <template v-if="selectedComponent.type === 'decor-border'">
              <ElFormItem label="样式">
                <ElSelect v-model="selectedComponent.option!.borderStyle">
                  <ElOption label="转角" value="corner" />
                  <ElOption label="线条" value="line" />
                  <ElOption label="辉光" value="glow" />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="强调色">
                <ElColorPicker v-model="selectedComponent.option!.accent" />
              </ElFormItem>
              <ElFormItem label="透明度">
                <ElSlider
                  v-model="selectedComponent.option!.opacity"
                  :min="0.1"
                  :max="1"
                  :step="0.05"
                />
              </ElFormItem>
            </template>
            <template v-if="selectedComponent.type === 'decor-flow-border'">
              <ElFormItem label="样式">
                <ElSelect v-model="selectedComponent.option!.variant">
                  <ElOption label="环绕" value="orbit" />
                  <ElOption label="横扫" value="scan" />
                  <ElOption label="转角" value="corner" />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="主色">
                <ElColorPicker v-model="selectedComponent.option!.accent" />
              </ElFormItem>
              <ElFormItem label="副色">
                <ElColorPicker v-model="selectedComponent.option!.secondary" />
              </ElFormItem>
              <ElFormItem label="速度秒">
                <ElInputNumber
                  v-model="selectedComponent.option!.speed"
                  :min="1"
                  :max="12"
                  :step="0.5"
                  step-strictly
                />
              </ElFormItem>
              <ElFormItem label="线宽">
                <ElInputNumber
                  v-model="selectedComponent.option!.thickness"
                  :min="1"
                  :max="8"
                  :step="1"
                  step-strictly
                />
              </ElFormItem>
              <ElFormItem label="辉光">
                <ElSwitch v-model="selectedComponent.option!.glow" />
              </ElFormItem>
              <ElFormItem label="透明度">
                <ElSlider
                  v-model="selectedComponent.option!.opacity"
                  :min="0.1"
                  :max="1"
                  :step="0.05"
                />
              </ElFormItem>
            </template>
            <template v-if="selectedComponent.type === 'decor-scanline'">
              <ElFormItem label="方向">
                <ElSelect v-model="selectedComponent.option!.direction">
                  <ElOption label="横向" value="horizontal" />
                  <ElOption label="纵向" value="vertical" />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="强调色">
                <ElColorPicker v-model="selectedComponent.option!.accent" />
              </ElFormItem>
              <ElFormItem label="速度秒">
                <ElInputNumber
                  v-model="selectedComponent.option!.speed"
                  :min="1"
                  :max="12"
                  :step="0.5"
                  step-strictly
                />
              </ElFormItem>
              <ElFormItem label="透明度">
                <ElSlider
                  v-model="selectedComponent.option!.opacity"
                  :min="0.1"
                  :max="1"
                  :step="0.05"
                />
              </ElFormItem>
            </template>
            <template v-if="selectedComponent.type === 'decor-title'">
              <ElFormItem label="主标题">
                <ElInput v-model="selectedComponent.option!.text" />
              </ElFormItem>
              <ElFormItem label="副标题">
                <ElInput v-model="selectedComponent.option!.subtitle" clearable />
              </ElFormItem>
              <ElFormItem label="样式">
                <ElSelect v-model="selectedComponent.option!.variant">
                  <ElOption label="标题栏" value="bar" />
                  <ElOption label="辉光" value="glow" />
                  <ElOption label="括号" value="bracket" />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="对齐">
                <ElSelect v-model="selectedComponent.option!.align">
                  <ElOption label="左对齐" value="left" />
                  <ElOption label="居中" value="center" />
                  <ElOption label="右对齐" value="right" />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="强调色">
                <ElColorPicker v-model="selectedComponent.option!.accent" />
              </ElFormItem>
            </template>
            <template v-if="selectedComponent.type === 'decor-divider'">
              <ElFormItem label="方向">
                <ElSelect v-model="selectedComponent.option!.direction">
                  <ElOption label="横向" value="horizontal" />
                  <ElOption label="纵向" value="vertical" />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="样式">
                <ElSelect v-model="selectedComponent.option!.variant">
                  <ElOption label="单线" value="line" />
                  <ElOption label="双线" value="double" />
                  <ElOption label="流光" value="pulse" />
                </ElSelect>
              </ElFormItem>
              <ElFormItem label="强调色">
                <ElColorPicker v-model="selectedComponent.option!.accent" />
              </ElFormItem>
              <ElFormItem label="速度秒">
                <ElInputNumber
                  v-model="selectedComponent.option!.speed"
                  :min="1"
                  :max="12"
                  :step="0.5"
                  step-strictly
                />
              </ElFormItem>
              <ElFormItem label="透明度">
                <ElSlider
                  v-model="selectedComponent.option!.opacity"
                  :min="0.1"
                  :max="1"
                  :step="0.05"
                />
              </ElFormItem>
            </template>
            <ElFormItem>
              <ElSpace>
                <ElButton @click="bringToFront">置顶</ElButton>
                <ElButton @click="sendSelectedToBottom">置底</ElButton>
                <ElButton type="danger" @click="removeSelected">删除</ElButton>
              </ElSpace>
            </ElFormItem>
          </ElForm>
        </template>
        <template v-else-if="selectedComponents.length > 1">
          <div class="panel-title">批量操作</div>
          <ElText type="info" size="small"> 已选择 {{ selectedComponents.length }} 个组件 </ElText>
          <ElSpace class="bulk-actions" direction="vertical" alignment="stretch">
            <div class="bulk-actions__section">
              <ElText type="info" size="small">编组</ElText>
              <div class="bulk-action-grid bulk-action-grid--two">
                <ElButton size="small" :disabled="!canGroup" @click="groupSelected">组合</ElButton>
                <ElButton size="small" :disabled="!canUngroup" @click="ungroupSelected">
                  取消组合
                </ElButton>
              </div>
            </div>
            <div class="bulk-actions__section">
              <ElText type="info" size="small">对齐</ElText>
              <div class="bulk-action-grid">
                <ElButton size="small" @click="alignSelected('left')">左</ElButton>
                <ElButton size="small" @click="alignSelected('center')">水平中</ElButton>
                <ElButton size="small" @click="alignSelected('right')">右</ElButton>
                <ElButton size="small" @click="alignSelected('top')">上</ElButton>
                <ElButton size="small" @click="alignSelected('middle')">垂直中</ElButton>
                <ElButton size="small" @click="alignSelected('bottom')">下</ElButton>
              </div>
            </div>
            <div class="bulk-actions__section">
              <ElText type="info" size="small">分布</ElText>
              <div class="bulk-action-grid">
                <ElButton
                  size="small"
                  :disabled="selectedComponents.length < 3"
                  @click="distributeSelected('horizontal')"
                >
                  水平
                </ElButton>
                <ElButton
                  size="small"
                  :disabled="selectedComponents.length < 3"
                  @click="distributeSelected('vertical')"
                >
                  垂直
                </ElButton>
              </div>
            </div>
            <ElButton @click="bringToFront">批量置顶</ElButton>
            <ElButton @click="sendSelectedToBottom">批量置底</ElButton>
            <ElButton type="danger" @click="removeSelected">批量删除</ElButton>
          </ElSpace>
        </template>
        <template v-else>
          <div class="panel-title">画布</div>
          <ElForm label-width="84px">
            <ElFormItem label="宽度">
              <ElInputNumber v-model="layout.canvas.width" :min="320" :max="7680" />
            </ElFormItem>
            <ElFormItem label="高度">
              <ElInputNumber v-model="layout.canvas.height" :min="240" :max="4320" />
            </ElFormItem>
            <ElFormItem label="背景色">
              <ElColorPicker v-model="screen.bg_config.color" />
            </ElFormItem>
            <ElFormItem label="主题">
              <ElSegmented v-model="screen.bg_config.theme" :options="boardThemeOptions" />
            </ElFormItem>
            <ElFormItem label="背景图">
              <ElInput v-model="screen.bg_config.image" clearable placeholder="图片 URL" />
            </ElFormItem>
            <ElFormItem label="图片适配">
              <ElSelect v-model="screen.bg_config.image_fit">
                <ElOption
                  v-for="item in backgroundFitOptions"
                  :key="item.value"
                  :label="item.label"
                  :value="item.value"
                />
              </ElSelect>
            </ElFormItem>
            <ElFormItem label="适配模式">
              <ElSegmented v-model="screen.bg_config.fit_mode" :options="fitModeOptions" />
            </ElFormItem>
            <ElFormItem label="显示对齐">
              <ElSegmented v-model="screen.bg_config.fit_align" :options="boardFitAlignOptions" />
            </ElFormItem>
          </ElForm>
        </template>
      </aside>
    </div>

    <ElDialog v-model="presetsVisible" title="预设模板" width="min(960px, 94vw)" destroy-on-close>
      <PresetGallery :type="presetType" @select="usePreset" />
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
  import { playerUrl } from '../core/player-url'
  import EditorToolbar from './EditorToolbar.vue'
  import PresetGallery from '../presets/PresetGallery.vue'
  import type { Preset, PresetType } from '../core/presets'
  import { placePresetComponents } from '../core/presets'
  import { createHistory } from '../core/history'
  import { LatestRequests } from '../core/latest'
  import { onBeforeRouteLeave, onBeforeRouteUpdate } from 'vue-router'
  import { useUserStore } from '@/store/modules/user'
  import { ElMessage, ElMessageBox } from 'element-plus'
  import Vue3DraggableResizable from 'vue3-draggable-resizable'
  import api from '../api/screen'
  import templateApi from '../api/query-template'
  import DraggableItem from '../widgets/DraggableItem.vue'
  import { normalizeBoardScale, resolveBoardFit } from '../widgets/fit'
  import {
    createDefaultComponent,
    decorWidgetTypes,
    getWidgetMeta,
    widgetRegistry
  } from '../widgets/registry'
  import {
    backgroundFitOptions,
    boardFitAlignOptions,
    boardCanvasStyle,
    boardThemeOptions,
    normalizeBgConfig
  } from '../widgets/theme'
  import type { BoardComponent, BoardLayout, BoardRect, BoardTableColumn } from '../widgets/types'

  interface PreviewState {
    rows: Record<string, any>[]
    error: string
    loading: boolean
  }

  interface DragPayload {
    x: number
    y: number
  }

  interface ResizePayload extends DragPayload {
    w: number
    h: number
  }

  interface SelectionBounds {
    left: number
    top: number
    right: number
    bottom: number
    width: number
    height: number
    centerX: number
    centerY: number
  }

  interface SelectionResizeState {
    bounds: SelectionBounds
    components: Array<{
      id: string
      rect: BoardRect
    }>
  }

  type SelectionMoveState = SelectionResizeState

  interface LayoutHistoryState {
    past: string[]
    future: string[]
    current: string
  }

  type AlignMode = 'left' | 'center' | 'right' | 'top' | 'middle' | 'bottom'
  type DistributeAxis = 'horizontal' | 'vertical'

  const route = useRoute()
  const router = useRouter()
  const zoom = ref<'auto' | number>('auto')
  const autoZoom = ref(0.75)
  const canvasShellRef = ref<HTMLElement>()
  const selectedId = ref('')
  const selectedIds = ref<string[]>([])
  const datasetParamsText = ref('{}')
  const copiedComponents = ref<BoardComponent[]>([])
  const presetsVisible = ref(false)
  const presetType = ref<PresetType>('screen')
  const templateOptions = ref<any[]>([])
  const previewMap = reactive<Record<string, PreviewState>>({})
  const layoutHistory = reactive<LayoutHistoryState>({
    past: [],
    future: [],
    current: ''
  })
  let canvasResizeObserver: ResizeObserver | undefined
  let suppressHistory = false
  let historyTimer = 0
  let selectionMoveState: SelectionMoveState | undefined
  let selectionResizeState: SelectionResizeState | undefined
  let syncingDatasetParamsText = false
  const history = createHistory(layoutHistory)
  const previewRequests = new LatestRequests()
  const busy = ref(false)
  const savedSnapshot = ref('')
  const widgetSearch = ref('')
  const filteredWidgets = computed(() =>
    widgetRegistry.filter((w) => w.name.includes(widgetSearch.value))
  )
  const dirty = computed(
    () => savedSnapshot.value !== '' && serializeLayout() !== savedSnapshot.value
  )
  const revision = ref(0)
  const loading = ref(true)
  let loadEpoch = 0
  let disposed = false
  let previewEpoch = 0
  const screen = reactive<any>({
    id: 0,
    name: '',
    status: 2,
    bg_config: normalizeBgConfig()
  })
  const layout = reactive<BoardLayout>({
    canvas: { width: 1920, height: 1080 },
    components: []
  })
  const sampleRows = [
    { label: '周一', value: 120 },
    { label: '周二', value: 180 },
    { label: '周三', value: 150 },
    { label: '周四', value: 220 },
    { label: '周五', value: 260 }
  ]
  const fitModeOptions = [
    { label: '完整显示', value: 'contain' },
    { label: '裁切铺满', value: 'cover' },
    { label: '非等比拉伸', value: 'stretch' }
  ]
  const imageFitOptions = [
    { label: '裁切铺满', value: 'cover' },
    { label: '完整显示', value: 'contain' },
    { label: '拉伸填满', value: 'fill' }
  ]
  const mapRegionOptions = [
    { label: '中国', value: 'china' },
    { label: '世界', value: 'world' }
  ]
  const tableAlignOptions: Array<{ label: string; value: NonNullable<BoardTableColumn['align']> }> =
    [
      { label: '左对齐', value: 'left' },
      { label: '居中', value: 'center' },
      { label: '右对齐', value: 'right' }
    ]
  const minComponentWidth = 120
  const minComponentHeight = 80
  const minRefreshSeconds = 10
  const maxRefreshSeconds = 3600
  const fieldMappingTypes = new Set([
    'art-bar-chart',
    'art-line-chart',
    'art-h-bar-chart',
    'art-dual-bar-compare-chart',
    'art-ring-chart',
    'art-radar-chart',
    'art-scatter-chart',
    'art-gauge-chart',
    'art-funnel-chart',
    'art-heatmap-chart',
    'stat-number',
    'data-table',
    'image-carousel',
    'geo-point-map'
  ])
  const kLineFieldOptionKeys = ['timeField', 'openField', 'closeField', 'highField', 'lowField']
  const timelineFieldOptionKeys = ['timeField', 'titleField', 'contentField', 'statusField']
  const alarmFieldOptionKeys = ['timeField', 'titleField', 'contentField', 'levelField']
  const progressRankFieldOptionKeys = ['labelField', 'valueField', 'targetField', 'statusField']
  const statusMatrixFieldOptionKeys = ['labelField', 'statusField', 'valueField', 'groupField']
  const decorTypes = decorWidgetTypes
  const componentNeedsData = (component: BoardComponent) => !decorTypes.has(component.type)
  const componentGroupId = (component?: BoardComponent) => String(component?.option?.groupId || '')
  const createGroupId = () => `g_${Date.now()}_${Math.floor(Math.random() * 1000)}`

  const selectedComponents = computed(() =>
    layout.components.filter((item) => selectedIds.value.includes(item.id))
  )
  const selectionBox = computed(() => {
    if (selectedComponents.value.length < 2) return undefined
    const bounds = selectedBounds()
    if (!bounds) return undefined

    return {
      x: bounds.left,
      y: bounds.top,
      w: Math.max(1, bounds.width),
      h: Math.max(1, bounds.height)
    }
  })
  const selectionResizeMin = computed(() => {
    const bounds = selectedBounds()
    if (!bounds) return { w: minComponentWidth, h: minComponentHeight }
    const minScaleX = minSelectionScale('x')
    const minScaleY = minSelectionScale('y')

    return {
      w: Math.max(minComponentWidth, Math.round(bounds.width * minScaleX)),
      h: Math.max(minComponentHeight, Math.round(bounds.height * minScaleY))
    }
  })
  const selectedComponent = computed(() =>
    selectedIds.value.length <= 1
      ? layout.components.find((item) => item.id === selectedId.value)
      : undefined
  )
  const layerItems = computed(() => orderedLayerComponents().slice().reverse())
  const requiresDataset = computed(() =>
    Boolean(selectedComponent.value && componentNeedsData(selectedComponent.value))
  )
  const selectedPreviewState = computed(() =>
    selectedComponent.value ? previewMap[selectedComponent.value.id] : undefined
  )
  const selectedPreviewRows = computed(() => selectedPreviewState.value?.rows || [])
  const selectedPreviewError = computed(() => selectedPreviewState.value?.error || '')
  const selectedPreviewLoading = computed(() => Boolean(selectedPreviewState.value?.loading))
  const fieldOptions = computed(() => Object.keys(selectedPreviewRows.value[0] || {}))
  const numericFieldOptions = computed(() =>
    fieldOptions.value.filter((field) =>
      selectedPreviewRows.value.some((row) => Number.isFinite(Number(row[field])))
    )
  )
  const supportsFieldMapping = computed(() =>
    Boolean(selectedComponent.value && fieldMappingTypes.has(selectedComponent.value.type))
  )
  const selectedTableColumns = computed(
    () => selectedComponent.value?.dataset.mapping?.tableColumns || []
  )
  const canUndo = computed(() => layoutHistory.past.length > 0)
  const canRedo = computed(() => layoutHistory.future.length > 0)
  const canCopy = computed(() => selectedComponents.value.length > 0)
  const canPaste = computed(() => copiedComponents.value.length > 0)
  const canGroup = computed(
    () => selectedComponents.value.length > 1 && !selectedComponentsInSingleGroup()
  )
  const canUngroup = computed(() =>
    selectedComponents.value.some((component) => Boolean(componentGroupId(component)))
  )
  const effectiveZoom = computed(() =>
    normalizeBoardScale(zoom.value === 'auto' ? autoZoom.value : zoom.value)
  )
  const scaledCanvasSize = computed(() => ({
    width: Math.max(1, Math.round(Number(layout.canvas.width || 1920) * effectiveZoom.value)),
    height: Math.max(1, Math.round(Number(layout.canvas.height || 1080) * effectiveZoom.value))
  }))
  const canvasStyle = computed(() => boardCanvasStyle(screen.bg_config))

  const loadData = async () => {
    const epoch = ++loadEpoch
    loading.value = true
    previewRequests.clear()
    suppressHistory = true
    try {
      const id = Number(route.params.id)
      const data = await api.read(id)
      if (disposed || epoch !== loadEpoch) return
      revision.value = Number(data.revision || 0)
      Object.assign(screen, data, { bg_config: normalizeBgConfig(data.bg_config) })
      const draft = normalizeLayout(data.draft_layout || data.layout || {})
      screen.bg_config = normalizeBgConfig(draft.bg_config || screen.bg_config)
      Object.assign(layout.canvas, draft.canvas)
      layout.components.splice(0, layout.components.length, ...draft.components)
      await nextTick()
      updateAutoZoom()
      resetLayoutHistory()
      savedSnapshot.value = serializeLayout()
      void refreshAllComponentData()
    } finally {
      if (epoch === loadEpoch) {
        suppressHistory = false
        loading.value = false
      }
    }
  }

  const loadTemplates = async () => {
    templateOptions.value = await templateApi.options()
  }

  const normalizeLayout = (value: any): BoardLayout => {
    const result: BoardLayout = {
      canvas: {
        width: Number(value?.canvas?.width || screen.width || 1920),
        height: Number(value?.canvas?.height || screen.height || 1080)
      },
      components: Array.isArray(value?.components)
        ? value.components.map((component: any, index: number) => {
            const type = component.type || 'art-bar-chart'
            const meta = getWidgetMeta(type)

            return {
              id: component.id || `w_${Date.now()}_${index}`,
              type,
              title: typeof component.title === 'string' ? component.title : meta?.name || '组件',
              rect: {
                x: Number(component.rect?.x || 0),
                y: Number(component.rect?.y || 0),
                w: Number(component.rect?.w || 320),
                h: Number(component.rect?.h || 180),
                z: Number(component.rect?.z || 1)
              },
              dataset: normalizeDataset(component.dataset),
              option: normalizeOption(type, component.option)
            }
          })
        : []
    }
    if (value?.bg_config && typeof value.bg_config === 'object') {
      result.bg_config = normalizeBgConfig(value.bg_config)
    }

    return result
  }

  const normalizeDatasetParams = (value: unknown, depth = 0): Record<string, any> => {
    if (!value || typeof value !== 'object' || Array.isArray(value)) return {}

    const result: Record<string, any> = {}
    const reserved = new Set(['code', 'cid', 'token', 'admin_preview', 'draft'])
    for (const [key, rawValue] of Object.entries(value as Record<string, any>).slice(0, 50)) {
      const name = key.trim()
      if (!/^[A-Za-z][A-Za-z0-9_]{0,63}$/.test(name)) continue
      if (reserved.has(name)) continue
      const normalized = normalizeDatasetParamValue(rawValue, depth)
      if (normalized !== undefined) {
        result[name] = normalized
      }
    }

    return result
  }

  const normalizeDatasetParamValue = (value: unknown, depth = 0): any => {
    if (value === null || typeof value === 'string' || typeof value === 'number') return value
    if (typeof value === 'boolean') return value
    if (Array.isArray(value)) {
      if (depth >= 4) return []
      return value.slice(0, 100).map((item) => normalizeDatasetParamValue(item, depth + 1))
    }
    if (typeof value === 'object') {
      if (depth >= 4) return {}
      return normalizeDatasetParams(value, depth + 1)
    }

    return undefined
  }

  const normalizeDataset = (dataset: any = {}) => {
    const rawColumns = normalizeTableColumns(dataset?.mapping?.tableColumns)
    const rawTableFields = normalizeTableFields(dataset?.mapping?.tableFields)
    const tableFields = rawTableFields.length
      ? rawTableFields
      : rawColumns.map((column) => column.field)

    return {
      queryTemplateId:
        Number(dataset?.queryTemplateId || dataset?.query_template_id || 0) || undefined,
      refresh: normalizeRefresh(dataset?.refresh),
      params: normalizeDatasetParams(dataset?.params || dataset?.runtimeParams || {}),
      mapping: {
        labelField: dataset?.mapping?.labelField || dataset?.fieldMap?.label || '',
        valueField: dataset?.mapping?.valueField || dataset?.fieldMap?.value || '',
        tableFields,
        tableColumns: normalizeTableColumns(dataset?.mapping?.tableColumns, tableFields)
      }
    }
  }

  const updateAutoZoom = () => {
    const shell = canvasShellRef.value
    if (!shell) return
    const fit = resolveBoardFit({
      viewportWidth: shell.clientWidth,
      viewportHeight: shell.clientHeight,
      canvasWidth: layout.canvas.width,
      canvasHeight: layout.canvas.height,
      mode: 'contain',
      padding: 32,
      maxScale: 1
    })
    autoZoom.value = fit.scaleX
  }

  const observeCanvasShell = () => {
    if (!canvasShellRef.value || !('ResizeObserver' in window)) return
    const observer = new ResizeObserver(updateAutoZoom)
    observer.observe(canvasShellRef.value)
    return observer
  }

  const addWidget = (type: string) => {
    const component = createDefaultComponent(type, layout.components.length) as BoardComponent
    if (layout.components.length >= 200) {
      ElMessage.warning('最多支持 200 个组件')
      return
    }
    component.rect.w = Math.min(component.rect.w, layout.canvas.width)
    component.rect.h = Math.min(component.rect.h, layout.canvas.height)
    component.rect.x = Math.max(
      0,
      Math.min(component.rect.x, layout.canvas.width - component.rect.w)
    )
    component.rect.y = Math.max(
      0,
      Math.min(component.rect.y, layout.canvas.height - component.rect.h)
    )
    ensureDataset(component)
    layout.components.push(component)
    setSingleSelection(component.id)
  }

  const setSingleSelection = (id: string) => {
    selectedId.value = id
    selectedIds.value = id ? [id] : []
  }

  const clearSelection = () => {
    selectedId.value = ''
    selectedIds.value = []
    selectionMoveState = undefined
    selectionResizeState = undefined
  }

  const selectionIdsForComponent = (id: string) => {
    const component = layout.components.find((item) => item.id === id)
    const groupId = componentGroupId(component)
    if (!groupId) return [id]

    return layout.components
      .filter((item) => componentGroupId(item) === groupId)
      .map((item) => item.id)
  }

  const selectedComponentsInSingleGroup = () => {
    const components = selectedComponents.value
    if (components.length < 2) return false
    const groupIds = components.map((component) => componentGroupId(component))

    return groupIds.every(Boolean) && new Set(groupIds).size === 1
  }

  const selectComponent = (id: string, additive = false) => {
    const targetIds = selectionIdsForComponent(id)
    if (!additive) {
      selectedIds.value = targetIds
      selectedId.value = targetIds[targetIds.length - 1] || ''
      return
    }

    const exists = targetIds.every((targetId) => selectedIds.value.includes(targetId))
    selectedIds.value = exists
      ? selectedIds.value.filter((item) => !targetIds.includes(item))
      : Array.from(new Set([...selectedIds.value, ...targetIds]))
    selectedId.value = exists
      ? selectedIds.value[selectedIds.value.length - 1] || ''
      : targetIds[targetIds.length - 1] || ''
  }

  const updateComponent = (component: BoardComponent) => {
    ensureDataset(component)
    const index = layout.components.findIndex((item) => item.id === component.id)
    if (index < 0) return

    const current = layout.components[index]
    const deltaX = Number(component.rect.x || 0) - Number(current.rect.x || 0)
    const deltaY = Number(component.rect.y || 0) - Number(current.rect.y || 0)
    const isPositionOnlyUpdate =
      Number(component.rect.w || 0) === Number(current.rect.w || 0) &&
      Number(component.rect.h || 0) === Number(current.rect.h || 0)
    const shouldMoveSelection =
      isPositionOnlyUpdate &&
      selectedIds.value.includes(component.id) &&
      selectedComponents.value.length > 1 &&
      (deltaX !== 0 || deltaY !== 0)

    if (shouldMoveSelection) {
      moveSelectedBy(deltaX, deltaY)
      return
    }

    layout.components.splice(index, 1, component)
  }

  const startComponentResize = () => {
    selectionMoveState = undefined
    selectionResizeState = undefined
  }

  const startSelectionDrag = () => {
    const bounds = selectedBounds()
    if (!bounds || selectedComponents.value.length < 2) return
    flushLayoutHistory()
    selectionMoveState = {
      bounds,
      components: selectedComponents.value.map((component) => ({
        id: component.id,
        rect: clonePlain(component.rect)
      }))
    }
    suppressHistory = true
  }

  const finishSelectionDrag = () => {
    if (!selectionMoveState) return
    selectionMoveState = undefined
    suppressHistory = false
    recordLayoutHistory()
  }

  const startSelectionResize = () => {
    const bounds = selectedBounds()
    if (!bounds || selectedComponents.value.length < 2) return
    flushLayoutHistory()
    selectionMoveState = undefined
    selectionResizeState = {
      bounds,
      components: selectedComponents.value.map((component) => ({
        id: component.id,
        rect: clonePlain(component.rect)
      }))
    }
    suppressHistory = true
  }

  const finishSelectionResize = () => {
    if (!selectionResizeState) return
    selectionResizeState = undefined
    suppressHistory = false
    recordLayoutHistory()
  }

  const dragSelectionBox = (payload: DragPayload) => {
    if (!selectionMoveState) startSelectionDrag()
    if (!selectionMoveState) return
    const scale = normalizeBoardScale(effectiveZoom.value)
    const rawX = Math.round(Number(payload.x || selectionMoveState.bounds.left))
    const rawY = Math.round(Number(payload.y || selectionMoveState.bounds.top))
    const desiredDeltaX = (rawX - selectionMoveState.bounds.left) / scale
    const desiredDeltaY = (rawY - selectionMoveState.bounds.top) / scale
    const delta = clampComponentDelta(selectionMoveState.components, desiredDeltaX, desiredDeltaY)

    for (const item of selectionMoveState.components) {
      const component = layout.components.find((target) => target.id === item.id)
      if (!component) continue
      component.rect.x = item.rect.x + delta.x
      component.rect.y = item.rect.y + delta.y
      clampComponentRect(component)
    }
  }

  const resizeSelectionBox = (payload: ResizePayload) => {
    if (!selectionResizeState) startSelectionResize()
    if (!selectionResizeState) return
    const bounds = normalizeSelectionResizeBounds(selectionResizeState, payload)
    const scaleX = bounds.width / selectionResizeState.bounds.width
    const scaleY = bounds.height / selectionResizeState.bounds.height

    for (const item of selectionResizeState.components) {
      const component = layout.components.find((target) => target.id === item.id)
      if (!component) continue
      component.rect.x = Math.round(
        bounds.left + (item.rect.x - selectionResizeState.bounds.left) * scaleX
      )
      component.rect.y = Math.round(
        bounds.top + (item.rect.y - selectionResizeState.bounds.top) * scaleY
      )
      component.rect.w = Math.max(minComponentWidth, Math.round(item.rect.w * scaleX))
      component.rect.h = Math.max(minComponentHeight, Math.round(item.rect.h * scaleY))
      clampComponentRect(component)
    }
  }

  const normalizeSelectionResizeBounds = (
    state: SelectionResizeState,
    payload: ResizePayload
  ): SelectionBounds => {
    const canvasWidth = Math.max(1, Number(layout.canvas.width || 0))
    const canvasHeight = Math.max(1, Number(layout.canvas.height || 0))
    const scale = normalizeBoardScale(effectiveZoom.value)
    const rawLeft = Math.round(
      state.bounds.left + (Number(payload.x || state.bounds.left) - state.bounds.left) / scale
    )
    const rawTop = Math.round(
      state.bounds.top + (Number(payload.y || state.bounds.top) - state.bounds.top) / scale
    )
    const rawWidth = Math.round(
      state.bounds.width + (Number(payload.w || state.bounds.width) - state.bounds.width) / scale
    )
    const rawHeight = Math.round(
      state.bounds.height + (Number(payload.h || state.bounds.height) - state.bounds.height) / scale
    )
    const rawRight = rawLeft + rawWidth
    const rawBottom = rawTop + rawHeight
    const minWidth = Math.round(state.bounds.width * minSelectionScale('x', state.components))
    const minHeight = Math.round(state.bounds.height * minSelectionScale('y', state.components))
    const width = Math.min(canvasWidth, Math.max(minComponentWidth, minWidth, rawWidth))
    const height = Math.min(canvasHeight, Math.max(minComponentHeight, minHeight, rawHeight))
    const leftMoved = rawLeft !== state.bounds.left
    const rightMoved = rawRight !== state.bounds.right
    const topMoved = rawTop !== state.bounds.top
    const bottomMoved = rawBottom !== state.bounds.bottom
    const maxLeft = Math.max(0, canvasWidth - width)
    const maxTop = Math.max(0, canvasHeight - height)
    const left = clampNumber(leftMoved && !rightMoved ? rawRight - width : rawLeft, 0, maxLeft)
    const top = clampNumber(topMoved && !bottomMoved ? rawBottom - height : rawTop, 0, maxTop)

    return createSelectionBounds(left, top, left + width, top + height)
  }

  const ensureDataset = (component: BoardComponent) => {
    component.dataset = normalizeDataset(component.dataset)
    component.option = normalizeOption(component.type, component.option)
  }

  const normalizeOption = (type: string, option: Record<string, any> = {}) => ({
    ...(getWidgetMeta(type)?.defaultOption || {}),
    ...(option || {})
  })

  const normalizeRefresh = (refresh: unknown) => {
    const seconds = Number(refresh || 30)
    if (!Number.isFinite(seconds) || seconds <= 0) return 30
    return Math.min(maxRefreshSeconds, Math.max(minRefreshSeconds, Math.round(seconds)))
  }

  const normalizeTableFields = (value: unknown): string[] => {
    if (!Array.isArray(value)) return []

    return Array.from(
      new Set(value.map((field) => String(field || '').trim()).filter(Boolean))
    ).slice(0, 8)
  }

  const normalizeTableColumns = (value: unknown, fields: string[] = []): BoardTableColumn[] => {
    const configured = new Map<string, BoardTableColumn>()
    if (Array.isArray(value)) {
      for (const item of value) {
        const field =
          typeof item === 'string'
            ? item.trim()
            : String((item as Record<string, any>)?.field || '').trim()
        if (!field || configured.has(field)) continue
        configured.set(field, {
          field,
          label:
            typeof (item as Record<string, any>)?.label === 'string'
              ? String((item as Record<string, any>).label).trim()
              : '',
          width: normalizeTableColumnWidth((item as Record<string, any>)?.width),
          align: normalizeTableColumnAlign((item as Record<string, any>)?.align)
        })
      }
    }

    const orderedFields = fields.length ? fields : Array.from(configured.keys())
    return orderedFields
      .map((field) => {
        const config = configured.get(field)

        return {
          field,
          label: config?.label || '',
          width: config?.width,
          align: config?.align || 'left'
        }
      })
      .slice(0, 8)
  }

  const normalizeTableColumnWidth = (value: unknown) => {
    if (value === '' || value === null || value === undefined) return undefined
    const width = Number(value)
    if (!Number.isFinite(width) || width <= 0) return undefined
    return Math.min(600, Math.max(60, Math.round(width)))
  }

  const normalizeTableColumnAlign = (value: unknown): NonNullable<BoardTableColumn['align']> =>
    value === 'center' || value === 'right' ? value : 'left'

  const clonePlain = <T,>(value: T): T => JSON.parse(JSON.stringify(value))

  const formatDatasetParams = (params: unknown) =>
    JSON.stringify(normalizeDatasetParams(params), null, 2)

  const syncDatasetParamsText = (component = selectedComponent.value) => {
    syncingDatasetParamsText = true
    datasetParamsText.value = formatDatasetParams(component?.dataset?.params || {})
    nextTick(() => {
      syncingDatasetParamsText = false
    })
  }

  const parseDatasetParamsText = (showError = false): Record<string, any> | undefined => {
    try {
      const parsed = datasetParamsText.value.trim() ? JSON.parse(datasetParamsText.value) : {}
      if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
        throw new Error('params must be object')
      }

      return normalizeDatasetParams(parsed)
    } catch {
      if (showError) ElMessage.error('查询参数必须是 JSON 对象')
      return undefined
    }
  }

  const applySelectedDatasetParams = () => {
    if (!selectedComponent.value || !requiresDataset.value) return true
    const params = parseDatasetParamsText(true)
    if (!params) return false
    selectedComponent.value.dataset.params = params
    syncDatasetParamsText(selectedComponent.value)
    queueLayoutHistory()
    return true
  }

  const serializeLayout = () =>
    JSON.stringify({
      canvas: clonePlain(layout.canvas),
      bg_config: clonePlain(screen.bg_config),
      components: clonePlain(layout.components)
    })

  const clearHistoryTimer = () => {
    if (!historyTimer) return
    window.clearTimeout(historyTimer)
    historyTimer = 0
  }

  const resetLayoutHistory = () => {
    clearHistoryTimer()
    history.reset(serializeLayout())
  }

  const recordLayoutHistory = () => {
    if (!suppressHistory) history.record(serializeLayout())
  }

  const queueLayoutHistory = () => {
    if (suppressHistory) return
    clearHistoryTimer()
    historyTimer = window.setTimeout(() => {
      historyTimer = 0
      recordLayoutHistory()
    }, 180)
  }

  const flushLayoutHistory = () => {
    if (!historyTimer) return
    clearHistoryTimer()
    recordLayoutHistory()
  }

  const restoreLayoutSnapshot = (snapshot: string) => {
    suppressHistory = true
    const nextLayout = normalizeLayout(JSON.parse(snapshot))
    Object.assign(layout.canvas, nextLayout.canvas)
    screen.bg_config = normalizeBgConfig(nextLayout.bg_config || screen.bg_config)
    layout.components.splice(0, layout.components.length, ...nextLayout.components)
    pruneSelection()
    // Release suppression immediately; undo never waits for remote data.
    layoutHistory.current = serializeLayout()
    suppressHistory = false
    previewRequests.clear()
    void nextTick(() => {
      updateAutoZoom()
      void refreshAllComponentData()
    })
  }

  const pruneSelection = () => {
    const ids = new Set(layout.components.map((component) => component.id))
    selectedIds.value = selectedIds.value.filter((id) => ids.has(id))
    if (!ids.has(selectedId.value)) {
      selectedId.value = selectedIds.value[selectedIds.value.length - 1] || ''
    }
  }

  const undoLayout = () => {
    flushLayoutHistory()
    const previous = history.undo(serializeLayout())
    if (previous !== undefined) restoreLayoutSnapshot(previous)
  }

  const redoLayout = () => {
    clearHistoryTimer()
    const next = history.redo(serializeLayout())
    if (next !== undefined) restoreLayoutSnapshot(next)
  }

  const componentRows = (component: BoardComponent) => {
    if (!componentNeedsData(component)) return []
    if (!component.dataset?.queryTemplateId) {
      return ['alarm-list', 'progress-rank', 'status-matrix'].includes(component.type)
        ? []
        : sampleRows
    }
    return previewMap[component.id]?.rows || []
  }

  const componentError = (component: BoardComponent) => previewMap[component.id]?.error || ''

  const refreshComponentData = async (component: BoardComponent) => {
    ensureDataset(component)
    if (!componentNeedsData(component)) {
      delete previewMap[component.id]
      return
    }
    const queryTemplateId = Number(component.dataset.queryTemplateId || 0)
    if (!queryTemplateId) {
      delete previewMap[component.id]
      return
    }

    const request = previewRequests.start(component.id)
    previewMap[component.id] = {
      rows: previewMap[component.id]?.rows || [],
      error: '',
      loading: true
    }
    try {
      const result = await templateApi.preview(
        {
          id: queryTemplateId,
          params: normalizeDatasetParams(component.dataset.params || {})
        },
        request.signal
      )
      if (!request.current() || disposed || !layout.components.includes(component)) return
      previewMap[component.id] = {
        rows: result.rows || [],
        error: '',
        loading: false
      }
      syncMappingWithRows(component, result.rows || [])
    } catch (error: any) {
      if (!request.current() || disposed || !layout.components.includes(component)) return
      previewMap[component.id] = {
        rows: previewMap[component.id]?.rows || [],
        error: error?.message || '预览失败',
        loading: false
      }
    } finally {
      request.finish()
    }
  }

  const refreshAllComponentData = async () => {
    const epoch = ++previewEpoch
    const pending = [...layout.components]
    let index = 0
    await Promise.all(
      Array.from({ length: Math.min(4, pending.length) }, async () => {
        while (index < pending.length && epoch === previewEpoch && !disposed) {
          const component = pending[index++]
          if (layout.components.includes(component)) await refreshComponentData(component)
        }
      })
    )
  }

  const onTemplateChange = async () => {
    if (!selectedComponent.value) return
    selectedComponent.value.dataset.mapping = {
      labelField: '',
      valueField: '',
      tableFields: [],
      tableColumns: []
    }
    if (selectedComponent.value.type === 'art-dual-bar-compare-chart') {
      selectedComponent.value.option!.positiveField = ''
      selectedComponent.value.option!.negativeField = ''
    }
    if (selectedComponent.value.type === 'art-k-line-chart') {
      resetOptionFields(selectedComponent.value, kLineFieldOptionKeys)
    }
    if (selectedComponent.value.type === 'event-timeline') {
      resetOptionFields(selectedComponent.value, timelineFieldOptionKeys)
    }
    if (selectedComponent.value.type === 'alarm-list') {
      resetOptionFields(selectedComponent.value, alarmFieldOptionKeys)
    }
    if (selectedComponent.value.type === 'progress-rank') {
      resetOptionFields(selectedComponent.value, progressRankFieldOptionKeys)
    }
    if (selectedComponent.value.type === 'status-matrix') {
      resetOptionFields(selectedComponent.value, statusMatrixFieldOptionKeys)
    }
    await refreshComponentData(selectedComponent.value)
  }

  const previewSelectedData = async () => {
    if (!selectedComponent.value) return
    if (!applySelectedDatasetParams()) return
    await refreshComponentData(selectedComponent.value)
  }

  const syncMappingWithRows = (component: BoardComponent, rows: Record<string, any>[]) => {
    ensureDataset(component)
    const fields = Object.keys(rows[0] || {})
    if (!fields.length) return
    const mapping = component.dataset.mapping!
    if (mapping.labelField && !fields.includes(mapping.labelField)) mapping.labelField = ''
    if (mapping.valueField && !fields.includes(mapping.valueField)) mapping.valueField = ''
    if (Array.isArray(mapping.tableFields)) {
      mapping.tableFields = mapping.tableFields.filter((field) => fields.includes(field))
    } else {
      mapping.tableFields = []
    }
    mapping.tableColumns = normalizeTableColumns(mapping.tableColumns, mapping.tableFields).filter(
      (column) => fields.includes(column.field)
    )
    if (component.type === 'art-dual-bar-compare-chart') {
      if (component.option?.positiveField && !fields.includes(component.option.positiveField)) {
        component.option.positiveField = ''
      }
      if (component.option?.negativeField && !fields.includes(component.option.negativeField)) {
        component.option.negativeField = ''
      }
    }
    if (component.type === 'art-k-line-chart') {
      resetOptionFields(component, kLineFieldOptionKeys, fields)
    }
    if (component.type === 'event-timeline') {
      resetOptionFields(component, timelineFieldOptionKeys, fields)
    }
    if (component.type === 'alarm-list') {
      resetOptionFields(component, alarmFieldOptionKeys, fields)
    }
    if (component.type === 'progress-rank') {
      resetOptionFields(component, progressRankFieldOptionKeys, fields)
    }
    if (component.type === 'status-matrix') {
      resetOptionFields(component, statusMatrixFieldOptionKeys, fields)
    }
  }

  const resetOptionFields = (component: BoardComponent, keys: string[], fields?: string[]) => {
    ensureDataset(component)
    for (const key of keys) {
      const value = String(component.option?.[key] || '')
      if (!value) continue
      if (!fields || !fields.includes(value)) component.option![key] = ''
    }
  }

  const syncSelectedTableColumns = () => {
    if (!selectedComponent.value) return
    syncTableColumnConfigs(selectedComponent.value)
  }

  const syncTableColumnConfigs = (component: BoardComponent) => {
    ensureDataset(component)
    const mapping = component.dataset.mapping!
    mapping.tableFields = normalizeTableFields(mapping.tableFields)
    mapping.tableColumns = normalizeTableColumns(mapping.tableColumns, mapping.tableFields)
  }

  const copySelected = () => {
    if (!selectedComponents.value.length) return
    copiedComponents.value = selectedComponents.value.map((component) => clonePlain(component))
    ElMessage.success(`已复制 ${copiedComponents.value.length} 个组件`)
  }

  const pasteCopied = async () => {
    if (!copiedComponents.value.length) return
    const groupIdMap = new Map<string, string>()
    const components = copiedComponents.value.map((component, index) =>
      createPastedComponent(component, index, groupIdMap)
    )
    components.forEach(ensureDataset)
    layout.components.push(...components)
    selectedIds.value = components.map((component) => component.id)
    selectedId.value = components[components.length - 1]?.id || ''
    await Promise.all(
      components
        .filter((component) => componentNeedsData(component) && component.dataset.queryTemplateId)
        .map((component) => refreshComponentData(component))
    )
  }

  const openPresets = (type: PresetType) => {
    presetType.value = type
    presetsVisible.value = true
  }

  const usePreset = async (item: Preset) => {
    if (item.type === 'component') {
      await insertComponentTemplate(item)
    } else {
      try {
        await ElMessageBox.confirm('套用预设将替换当前画布，可通过撤销恢复。', '套用预设模板', {
          type: 'warning'
        })
      } catch {
        return
      }
      await applyScreenTemplate(item)
    }
    presetsVisible.value = false
  }

  const insertComponentTemplate = async (item: Preset) => {
    const components = presetComponents(item)
    if (!components.length) {
      ElMessage.warning('模板没有可插入组件')
      return
    }
    if (layout.components.length + components.length > 200) {
      ElMessage.warning('画布最多支持 200 个组件')
      return
    }
    flushLayoutHistory()
    suppressHistory = true
    const groupIdMap = new Map<string, string>()
    const nextComponents = placePresetComponents(
      components.map((component, index) => createTemplateComponent(component, index, groupIdMap)),
      layout.canvas,
      Math.max(0, ...layout.components.map((component) => component.rect.z || 1))
    )
    layout.components.push(...nextComponents)
    selectedIds.value = nextComponents.map((component) => component.id)
    selectedId.value = nextComponents[nextComponents.length - 1]?.id || ''
    suppressHistory = false
    recordLayoutHistory()
    await Promise.all(nextComponents.map((component) => refreshComponentData(component)))
    ElMessage.success('组件已插入')
  }

  const applyScreenTemplate = async (item: Preset) => {
    const nextLayout = normalizeLayout(item.content?.layout || item.content)
    flushLayoutHistory()
    suppressHistory = true
    Object.assign(layout.canvas, nextLayout.canvas)
    screen.bg_config = normalizeBgConfig(nextLayout.bg_config || screen.bg_config)
    const groupIdMap = new Map<string, string>()
    const components = nextLayout.components.map((component, index) =>
      createTemplateComponent(component, index, groupIdMap)
    )
    layout.components.splice(0, layout.components.length, ...components)
    clearSelection()
    suppressHistory = false
    recordLayoutHistory()
    await nextTick()
    updateAutoZoom()
    await refreshAllComponentData()
    ElMessage.success('模板已套用')
  }

  const presetComponents = (item: Preset): BoardComponent[] => {
    const content = item.content || {}
    if (Array.isArray(content.components)) {
      return normalizeLayout({ canvas: layout.canvas, components: content.components }).components
    }

    return normalizeLayout(content.layout || content).components
  }

  const createTemplateComponent = (
    source: BoardComponent,
    index: number,
    groupIdMap: Map<string, string>
  ): BoardComponent => {
    const component = clonePlain(source)
    const groupId = componentGroupId(component)
    component.id = `w_${Date.now()}_${Math.floor(Math.random() * 1000)}_${index}`
    if (groupId) {
      if (!groupIdMap.has(groupId)) groupIdMap.set(groupId, createGroupId())
      component.option = { ...(component.option || {}), groupId: groupIdMap.get(groupId) }
    }
    component.rect.z = Number(component.rect.z || index + 1)

    const normalized = normalizeLayout({
      canvas: layout.canvas,
      components: [component]
    }).components[0]
    ensureDataset(normalized)
    normalized.dataset.queryTemplateId = undefined
    return normalized
  }

  const createPastedComponent = (
    source: BoardComponent,
    index = 0,
    groupIdMap: Map<string, string> = new Map()
  ): BoardComponent => {
    const component = clonePlain(source)
    const maxZ = Math.max(0, ...layout.components.map((item) => Number(item.rect.z || 1)))
    const offset = 24 + index * 12
    const maxX = Math.max(0, Number(layout.canvas.width || 0) - Number(component.rect.w || 0))
    const maxY = Math.max(0, Number(layout.canvas.height || 0) - Number(component.rect.h || 0))
    const groupId = componentGroupId(component)

    component.id = `w_${Date.now()}_${Math.floor(Math.random() * 1000)}`
    component.title = component.title ? `${component.title}副本` : component.title
    component.rect.x = Math.min(maxX, Math.max(0, Number(component.rect.x || 0) + offset))
    component.rect.y = Math.min(maxY, Math.max(0, Number(component.rect.y || 0) + offset))
    component.rect.z = maxZ + index + 1
    if (groupId) {
      if (!groupIdMap.has(groupId)) groupIdMap.set(groupId, createGroupId())
      component.option = { ...(component.option || {}), groupId: groupIdMap.get(groupId) }
    }

    return normalizeLayout({ canvas: layout.canvas, components: [component] }).components[0]
  }

  const groupSelected = () => {
    if (!canGroup.value) return
    flushLayoutHistory()
    const groupId = createGroupId()
    selectedComponents.value.forEach((component) => {
      component.option = { ...(component.option || {}), groupId }
    })
    recordLayoutHistory()
  }

  const ungroupSelected = () => {
    if (!canUngroup.value) return
    flushLayoutHistory()
    selectedComponents.value.forEach((component) => {
      if (!component.option) return
      delete component.option.groupId
    })
    recordLayoutHistory()
  }

  const componentName = (component: BoardComponent) =>
    getWidgetMeta(component.type)?.name || component.type || '组件'

  const selectLayer = (id: string, event?: MouseEvent) => {
    selectComponent(id, Boolean(event?.shiftKey || event?.metaKey || event?.ctrlKey))
  }

  const orderedLayerComponents = () =>
    layout.components.slice().sort((a, b) => {
      const diff = Number(a.rect.z || 1) - Number(b.rect.z || 1)
      if (diff !== 0) return diff
      return layout.components.indexOf(a) - layout.components.indexOf(b)
    })

  const normalizeLayerOrder = (ordered: BoardComponent[]) => {
    ordered.forEach((component, index) => {
      component.rect.z = index + 1
    })
  }

  const normalizedLayoutForSave = (): BoardLayout => {
    const payloadLayout = clonePlain(layout)
    payloadLayout.bg_config = normalizeBgConfig(screen.bg_config)
    const ordered = payloadLayout.components.slice().sort((a, b) => {
      const diff = Number(a.rect.z || 1) - Number(b.rect.z || 1)
      if (diff !== 0) return diff
      return payloadLayout.components.indexOf(a) - payloadLayout.components.indexOf(b)
    })
    ordered.forEach((component, index) => {
      component.rect.z = index + 1
    })

    return payloadLayout
  }

  const isLayerTop = (component: BoardComponent) => {
    const ordered = orderedLayerComponents()
    const selected = new Set(selectionIdsForComponent(component.id))
    const positions = ordered
      .map((item, index) => (selected.has(item.id) ? index : -1))
      .filter((index) => index >= 0)
    if (!positions.length) return true
    const topIndex = Math.max(...positions)

    return !ordered.slice(topIndex + 1).some((item) => !selected.has(item.id))
  }

  const isLayerBottom = (component: BoardComponent) => {
    const ordered = orderedLayerComponents()
    const selected = new Set(selectionIdsForComponent(component.id))
    const positions = ordered
      .map((item, index) => (selected.has(item.id) ? index : -1))
      .filter((index) => index >= 0)
    if (!positions.length) return true
    const bottomIndex = Math.min(...positions)

    return !ordered.slice(0, bottomIndex).some((item) => !selected.has(item.id))
  }

  const moveLayer = (component: BoardComponent, direction: 'up' | 'down') => {
    flushLayoutHistory()
    const ordered = orderedLayerComponents()
    const moveIds = selectionIdsForComponent(component.id)
    const moveSet = new Set(moveIds)
    if (moveSet.size <= 1) {
      const index = ordered.findIndex((item) => item.id === component.id)
      const targetIndex = direction === 'up' ? index + 1 : index - 1
      if (index < 0 || targetIndex < 0 || targetIndex >= ordered.length) {
        selectComponent(component.id)
        return
      }
      ;[ordered[index], ordered[targetIndex]] = [ordered[targetIndex], ordered[index]]
      normalizeLayerOrder(ordered)
      selectComponent(component.id)
      recordLayoutHistory()
      return
    }

    const nextOrdered = moveLayerBlock(ordered, moveSet, direction)
    if (!nextOrdered) {
      selectComponent(component.id)
      return
    }
    normalizeLayerOrder(nextOrdered)
    selectComponent(component.id)
    recordLayoutHistory()
  }

  const moveLayerBlock = (
    ordered: BoardComponent[],
    moveSet: Set<string>,
    direction: 'up' | 'down'
  ) => {
    const positions = ordered
      .map((item, index) => (moveSet.has(item.id) ? index : -1))
      .filter((index) => index >= 0)
    if (!positions.length) return undefined

    const moving = ordered.filter((item) => moveSet.has(item.id))
    const others = ordered.filter((item) => !moveSet.has(item.id))
    if (direction === 'up') {
      const topIndex = Math.max(...positions)
      const next = ordered.slice(topIndex + 1).find((item) => !moveSet.has(item.id))
      if (!next) return undefined
      const insertIndex = others.findIndex((item) => item.id === next.id) + 1
      others.splice(insertIndex, 0, ...moving)
      return others
    }

    const bottomIndex = Math.min(...positions)
    const previous = ordered
      .slice(0, bottomIndex)
      .reverse()
      .find((item) => !moveSet.has(item.id))
    if (!previous) return undefined
    const insertIndex = others.findIndex((item) => item.id === previous.id)
    others.splice(insertIndex, 0, ...moving)

    return others
  }

  const sendLayerGroupToEdge = (component: BoardComponent, edge: 'top' | 'bottom') => {
    flushLayoutHistory()
    const selected = new Set(selectionIdsForComponent(component.id))
    const ordered = orderedLayerComponents()
    const moving = ordered.filter((item) => selected.has(item.id))
    if (!moving.length) return
    const rest = ordered.filter((item) => !selected.has(item.id))
    const nextOrdered = edge === 'top' ? [...rest, ...moving] : [...moving, ...rest]
    normalizeLayerOrder(nextOrdered)
    selectComponent(component.id)
    recordLayoutHistory()
  }

  const sendLayerToTop = (component: BoardComponent) => {
    if (componentGroupId(component)) {
      sendLayerGroupToEdge(component, 'top')
      return
    }
    flushLayoutHistory()
    const ordered = orderedLayerComponents().filter((item) => item.id !== component.id)
    ordered.push(component)
    normalizeLayerOrder(ordered)
    selectComponent(component.id)
    recordLayoutHistory()
  }

  const sendLayerToBottom = (component: BoardComponent) => {
    if (componentGroupId(component)) {
      sendLayerGroupToEdge(component, 'bottom')
      return
    }
    flushLayoutHistory()
    const ordered = orderedLayerComponents().filter((item) => item.id !== component.id)
    ordered.unshift(component)
    normalizeLayerOrder(ordered)
    selectComponent(component.id)
    recordLayoutHistory()
  }

  const bringToFront = () => {
    if (!selectedComponents.value.length) return
    if (selectedComponents.value.length === 1) {
      sendLayerToTop(selectedComponents.value[0])
      return
    }
    flushLayoutHistory()
    const selected = new Set(selectedIds.value)
    const ordered = orderedLayerComponents()
    normalizeLayerOrder([
      ...ordered.filter((component) => !selected.has(component.id)),
      ...ordered.filter((component) => selected.has(component.id))
    ])
    recordLayoutHistory()
  }

  const sendSelectedToBottom = () => {
    if (!selectedComponents.value.length) return
    if (selectedComponents.value.length === 1) {
      sendLayerToBottom(selectedComponents.value[0])
      return
    }
    flushLayoutHistory()
    const selected = new Set(selectedIds.value)
    const ordered = orderedLayerComponents()
    normalizeLayerOrder([
      ...ordered.filter((component) => selected.has(component.id)),
      ...ordered.filter((component) => !selected.has(component.id))
    ])
    recordLayoutHistory()
  }

  const moveSelectedBy = (deltaX: number, deltaY: number) => {
    const selected = new Set(selectedIds.value)
    const delta = clampComponentDelta(selectedComponents.value, deltaX, deltaY)
    if (delta.x === 0 && delta.y === 0) return

    for (const component of layout.components) {
      if (!selected.has(component.id)) continue
      component.rect.x += delta.x
      component.rect.y += delta.y
      clampComponentRect(component)
    }
  }

  const clampComponentDelta = (
    components: Array<{ rect: BoardRect }>,
    deltaX: number,
    deltaY: number
  ) => {
    if (!components.length) return { x: 0, y: 0 }

    const minDeltaX = Math.max(...components.map((component) => -Number(component.rect.x || 0)))
    const maxDeltaX = Math.min(
      ...components.map(
        (component) =>
          Math.max(0, Number(layout.canvas.width || 0) - Number(component.rect.w || 0)) -
          Number(component.rect.x || 0)
      )
    )
    const minDeltaY = Math.max(...components.map((component) => -Number(component.rect.y || 0)))
    const maxDeltaY = Math.min(
      ...components.map(
        (component) =>
          Math.max(0, Number(layout.canvas.height || 0) - Number(component.rect.h || 0)) -
          Number(component.rect.y || 0)
      )
    )

    return {
      x: clampNumber(Math.round(deltaX), minDeltaX, maxDeltaX),
      y: clampNumber(Math.round(deltaY), minDeltaY, maxDeltaY)
    }
  }

  const alignSelected = (mode: AlignMode) => {
    if (selectedComponents.value.length < 2) return
    flushLayoutHistory()
    const bounds = selectedBounds()
    if (!bounds) return

    for (const component of selectedComponents.value) {
      if (mode === 'left') {
        component.rect.x = bounds.left
      } else if (mode === 'center') {
        component.rect.x = Math.round(bounds.centerX - component.rect.w / 2)
      } else if (mode === 'right') {
        component.rect.x = bounds.right - component.rect.w
      } else if (mode === 'top') {
        component.rect.y = bounds.top
      } else if (mode === 'middle') {
        component.rect.y = Math.round(bounds.centerY - component.rect.h / 2)
      } else if (mode === 'bottom') {
        component.rect.y = bounds.bottom - component.rect.h
      }
      clampComponentRect(component)
    }

    recordLayoutHistory()
  }

  const distributeSelected = (axis: DistributeAxis) => {
    if (selectedComponents.value.length < 3) return
    flushLayoutHistory()
    const sorted = selectedComponents.value.slice().sort((a, b) => {
      const aCenter = axis === 'horizontal' ? a.rect.x + a.rect.w / 2 : a.rect.y + a.rect.h / 2
      const bCenter = axis === 'horizontal' ? b.rect.x + b.rect.w / 2 : b.rect.y + b.rect.h / 2
      return aCenter - bCenter
    })
    const first = sorted[0]
    const last = sorted[sorted.length - 1]
    const firstCenter =
      axis === 'horizontal' ? first.rect.x + first.rect.w / 2 : first.rect.y + first.rect.h / 2
    const lastCenter =
      axis === 'horizontal' ? last.rect.x + last.rect.w / 2 : last.rect.y + last.rect.h / 2
    const gap = (lastCenter - firstCenter) / (sorted.length - 1)

    sorted.slice(1, -1).forEach((component, middleIndex) => {
      const index = middleIndex + 1
      const center = firstCenter + gap * index
      if (axis === 'horizontal') {
        component.rect.x = Math.round(center - component.rect.w / 2)
      } else {
        component.rect.y = Math.round(center - component.rect.h / 2)
      }
      clampComponentRect(component)
    })
    recordLayoutHistory()
  }

  const selectedBounds = (): SelectionBounds | undefined => {
    const components = selectedComponents.value
    if (!components.length) return undefined
    const left = Math.min(...components.map((component) => component.rect.x))
    const top = Math.min(...components.map((component) => component.rect.y))
    const right = Math.max(...components.map((component) => component.rect.x + component.rect.w))
    const bottom = Math.max(...components.map((component) => component.rect.y + component.rect.h))

    return createSelectionBounds(left, top, right, bottom)
  }

  const createSelectionBounds = (
    left: number,
    top: number,
    right: number,
    bottom: number
  ): SelectionBounds => {
    const width = Math.max(1, Math.round(right - left))
    const height = Math.max(1, Math.round(bottom - top))

    return {
      left: Math.round(left),
      top: Math.round(top),
      right: Math.round(left) + width,
      bottom: Math.round(top) + height,
      width,
      height,
      centerX: Math.round(left) + width / 2,
      centerY: Math.round(top) + height / 2
    }
  }

  const minSelectionScale = (
    axis: 'x' | 'y',
    components: Array<{ rect: BoardRect }> = selectedComponents.value
  ) => {
    if (!components.length) return 1
    const minSize = axis === 'x' ? minComponentWidth : minComponentHeight
    const field = axis === 'x' ? 'w' : 'h'

    return Math.max(
      0,
      ...components.map((component) => {
        const size = Number(component.rect[field] || minSize)
        if (size <= 0) return 1
        return minSize / size
      })
    )
  }

  const clampComponentRect = (component: BoardComponent) => {
    const maxX = Math.max(0, Number(layout.canvas.width || 0) - Number(component.rect.w || 0))
    const maxY = Math.max(0, Number(layout.canvas.height || 0) - Number(component.rect.h || 0))
    component.rect.x = Math.min(maxX, Math.max(0, Math.round(component.rect.x)))
    component.rect.y = Math.min(maxY, Math.max(0, Math.round(component.rect.y)))
  }

  const clampNumber = (value: number, min: number, max: number) =>
    Math.min(max, Math.max(min, value))

  const removeSelected = () => {
    if (!selectedIds.value.length) return
    flushLayoutHistory()
    const selected = new Set(selectedIds.value)
    for (let index = layout.components.length - 1; index >= 0; index--) {
      if (selected.has(layout.components[index].id)) {
        delete previewMap[layout.components[index].id]
        layout.components.splice(index, 1)
      }
    }
    clearSelection()
    recordLayoutHistory()
  }

  const exportLayout = () => {
    if (!applySelectedDatasetParams()) return
    const file = new Blob(
      [
        JSON.stringify(
          { format: 'boardstudio', version: 1, layout: normalizedLayoutForSave() },
          null,
          2
        )
      ],
      { type: 'application/json' }
    )
    const url = URL.createObjectURL(file)
    const link = document.createElement('a')
    link.href = url
    link.download = `boardstudio-${screen.code || screen.id}.json`
    link.click()
    setTimeout(() => URL.revokeObjectURL(url), 1000)
  }
  const importLayout = () => {
    const input = document.createElement('input')
    input.type = 'file'
    input.accept = '.json,application/json'
    input.onchange = async () => {
      const file = input.files?.[0]
      if (!file) return
      try {
        if (file.size > 2 * 1024 * 1024) throw new Error('布局文件不能超过 2 MB')
        const document = JSON.parse(await file.text())
        if (
          document.format !== 'boardstudio' ||
          document.version !== 1 ||
          !Array.isArray(document.layout?.components)
        )
          throw new Error('不是 Board Studio v1 布局文件')
        if (document.layout.components.length > 200) throw new Error('最多支持 200 个组件')
        await ElMessageBox.confirm('导入将替换当前画布，之后可撤销。', '导入布局', {
          type: 'warning'
        })
        flushLayoutHistory()
        const imported = normalizeLayout(document.layout)
        // Datasource IDs belong to a particular installation and must be rebound.
        for (const component of imported.components) component.dataset.queryTemplateId = undefined
        const before = serializeLayout()
        restoreLayoutSnapshot(JSON.stringify(imported))
        layoutHistory.current = before
        recordLayoutHistory()
        ElMessage.success('已导入，请重新绑定查询模板后保存')
      } catch (error: any) {
        if (error !== 'cancel' && error !== 'close')
          ElMessage.error(error?.message || '布局导入失败')
      }
    }
    input.click()
  }

  const persistDraftLayout = async () => {
    flushLayoutHistory()
    const snapshot = serializeLayout()
    const result = await api.saveLayout({
      id: screen.id,
      layout: normalizedLayoutForSave(),
      revision: revision.value
    })
    revision.value = result.revision
    savedSnapshot.value = snapshot
    return revision.value
  }

  const runWrite = async (action: () => Promise<void>) => {
    if (busy.value || loading.value || !applySelectedDatasetParams()) return
    busy.value = true
    try {
      await action()
    } finally {
      busy.value = false
    }
  }
  const saveLayout = () =>
    runWrite(async () => {
      await persistDraftLayout()
      ElMessage.success('保存成功')
    })

  const openDraftPreview = () => {
    if (busy.value || !screen.code || !applySelectedDatasetParams()) return
    // Open in the click handler to avoid browser popup blocking after await.
    const preview = window.open('about:blank', '_blank')
    if (!preview) {
      ElMessage.warning('请允许此站点打开预览窗口')
      return
    }
    preview.opener = null
    void runWrite(async () => {
      try {
        await persistDraftLayout()
        const url = playerUrl(String(screen.code))
        url.searchParams.set('code', screen.code)
        url.searchParams.set('admin_preview', '1')
        url.searchParams.set('draft', '1')
        // JWT only in the fragment, never in the server request or access log.
        url.hash = new URLSearchParams({
          authorization: `Bearer ${useUserStore().accessToken}`
        }).toString()
        preview.location.replace(url.toString())
      } catch (error) {
        preview.close()
        throw error
      }
    })
  }

  const publish = () =>
    runWrite(async () => {
      const savedRevision = await persistDraftLayout()
      await api.publish({ id: screen.id, revision: savedRevision })
      screen.status = 1
      ElMessage.success(dirty.value ? '已发布保存的版本，当前还有新更改' : '发布成功')
    })

  const beforeUnload = (event: BeforeUnloadEvent) => {
    if (!dirty.value && !busy.value) return
    event.preventDefault()
    event.returnValue = ''
  }
  const keyboard = (event: KeyboardEvent) => {
    const target = event.target as HTMLElement
    if (event.isComposing || target.closest('input,textarea,select,[contenteditable=true]')) return
    const modifier = event.ctrlKey || event.metaKey
    if (modifier && event.key.toLowerCase() === 's') {
      event.preventDefault()
      void saveLayout()
      return
    }
    if (modifier && event.key.toLowerCase() === 'z') {
      event.preventDefault()
      if (event.shiftKey) redoLayout()
      else undoLayout()
      return
    }
    if (modifier && event.key.toLowerCase() === 'y') {
      event.preventDefault()
      redoLayout()
      return
    }
    if (modifier && event.key.toLowerCase() === 'c') {
      event.preventDefault()
      copySelected()
      return
    }
    if (modifier && event.key.toLowerCase() === 'v') {
      event.preventDefault()
      pasteCopied()
      return
    }
    if (event.key === 'Delete' || event.key === 'Backspace') {
      event.preventDefault()
      removeSelected()
    }
  }
  const confirmLeave = async () => {
    if (!dirty.value && !busy.value) return true
    if (busy.value) {
      ElMessage.warning('正在保存，请稍候')
      return false
    }
    try {
      await ElMessageBox.confirm('更改尚未保存，确定离开？', '未保存更改', { type: 'warning' })
      return true
    } catch {
      return false
    }
  }

  onBeforeRouteLeave(confirmLeave)
  onBeforeRouteUpdate(async (to, from) => to.params.id === from.params.id || (await confirmLeave()))
  watch(
    () => route.params.id,
    () => {
      void loadData()
    }
  )

  const back = () => {
    void router.push('/boardstudio/screen')
  }

  onMounted(async () => {
    window.addEventListener('beforeunload', beforeUnload)
    window.addEventListener('keydown', keyboard)
    await Promise.all([loadData(), loadTemplates()])
    canvasResizeObserver = observeCanvasShell()
    updateAutoZoom()
  })

  onBeforeUnmount(() => {
    disposed = true
    loadEpoch++
    previewEpoch++
    previewRequests.clear()
    window.removeEventListener('beforeunload', beforeUnload)
    window.removeEventListener('keydown', keyboard)
    clearHistoryTimer()
    canvasResizeObserver?.disconnect()
  })

  watch(selectedComponent, (component) => {
    if (component) {
      ensureDataset(component)
      syncDatasetParamsText(component)
      if (component.type === 'data-table') syncTableColumnConfigs(component)
    } else {
      syncDatasetParamsText()
    }
  })

  watch(
    () => selectedComponent.value?.type,
    () => {
      if (selectedComponent.value) {
        ensureDataset(selectedComponent.value)
        syncDatasetParamsText(selectedComponent.value)
        if (selectedComponent.value.type === 'data-table') {
          syncTableColumnConfigs(selectedComponent.value)
        }
      }
    }
  )

  watch(datasetParamsText, () => {
    if (syncingDatasetParamsText || !selectedComponent.value || !requiresDataset.value) return
    const params = parseDatasetParamsText(false)
    if (!params) return
    selectedComponent.value.dataset.params = params
    queueLayoutHistory()
  })

  watch(layout, queueLayoutHistory, { deep: true, flush: 'sync' })

  watch(() => screen.bg_config, queueLayoutHistory, { deep: true, flush: 'sync' })

  watch(() => [layout.canvas.width, layout.canvas.height], updateAutoZoom)
</script>

<style scoped lang="scss">
  .boardstudio-editor {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 84px);
    min-height: 480px;
    overflow: hidden;
    background: var(--default-bg-color);
  }

  .editor-main {
    display: grid;
    flex: 1;
    min-height: 0;
    grid-template-columns: 180px minmax(0, 1fr) 330px;
  }

  .widget-panel,
  .property-panel {
    min-height: 0;
    padding: 14px;
    overflow: auto;
    background: var(--default-box-color);
    border-right: 1px solid var(--default-border);
  }

  .property-panel {
    border-right: 0;
    border-left: 1px solid var(--default-border);
  }

  .property-panel :deep(.el-form-item__content) {
    min-width: 0;
  }
  .property-panel :deep(.el-segmented__group) {
    flex-wrap: wrap;
    row-gap: 4px;
  }
  .property-panel :deep(.el-segmented) {
    max-width: 100%;
  }

  .panel-title {
    margin-bottom: 12px;
    font-size: 14px;
    font-weight: 600;
  }

  .template-bind-row {
    display: grid;
    width: 100%;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 8px;
  }

  .table-column-config {
    display: flex;
    flex-direction: column;
    width: 100%;
    gap: 8px;
  }

  .table-column-config__item {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 8px;
    background: var(--default-bg-color);
    border: 1px solid var(--default-border);
    border-radius: 6px;
  }

  .table-column-config__field {
    width: 100%;
    font-size: 12px;
  }

  .table-column-config__controls {
    display: grid;
    grid-template-columns: 80px minmax(0, 1fr);
    gap: 6px;
  }

  .table-column-config__width,
  .table-column-config__align {
    width: 100%;
  }

  .bulk-actions {
    width: 100%;
    margin-top: 12px;
  }

  .bulk-actions__section {
    display: flex;
    flex-direction: column;
    width: 100%;
    gap: 8px;
  }

  .bulk-action-grid {
    display: grid;
    width: 100%;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 6px;
  }

  .bulk-action-grid--two {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .bulk-action-grid :deep(.el-button) {
    width: 100%;
    margin-left: 0;
  }

  .widget-button {
    display: flex;
    align-items: center;
    width: 100%;
    gap: 8px;
    height: 38px;
    padding: 0 10px;
    margin-bottom: 8px;
    color: var(--art-gray-800);
    cursor: pointer;
    background: transparent;
    border: 1px solid var(--default-border);
    border-radius: 6px;
  }

  .panel-title--layers {
    padding-top: 8px;
    margin-top: 10px;
    border-top: 1px solid var(--default-border);
  }

  .layer-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .layer-item {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto auto;
    gap: 4px 8px;
    padding: 8px;
    cursor: pointer;
    border: 1px solid var(--default-border);
    border-radius: 6px;
  }

  .layer-item.is-active {
    color: var(--el-color-primary);
    background: var(--el-color-primary-light-9);
    border-color: var(--el-color-primary-light-5);
  }

  .layer-item__name,
  .layer-item__type {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .layer-item__name {
    font-size: 13px;
    font-weight: 600;
  }

  .layer-item__type {
    grid-column: 1;
    font-size: 12px;
    color: var(--art-gray-500);
  }

  .layer-item__tag {
    grid-row: 1 / span 2;
    grid-column: 2;
    align-self: center;
    padding: 1px 5px;
    font-size: 11px;
    color: var(--el-color-primary);
    background: var(--el-color-primary-light-9);
    border: 1px solid var(--el-color-primary-light-5);
    border-radius: 4px;
  }

  .layer-item__actions {
    display: grid;
    grid-row: 1 / span 2;
    grid-column: 3;
    grid-template-columns: repeat(2, 24px);
    gap: 4px;
    align-self: center;
  }

  .layer-item__actions button {
    width: 24px;
    height: 22px;
    padding: 0;
    font-size: 12px;
    color: var(--art-gray-700);
    cursor: pointer;
    background: var(--default-box-color);
    border: 1px solid var(--default-border);
    border-radius: 4px;
  }

  .layer-item__actions button:disabled {
    cursor: not-allowed;
    opacity: 0.42;
  }

  .canvas-shell {
    min-width: 0;
    min-height: 0;
    overflow: auto;
    background:
      linear-gradient(45deg, rgb(255 255 255 / 4%) 25%, transparent 25%),
      linear-gradient(-45deg, rgb(255 255 255 / 4%) 25%, transparent 25%), #111827;
    background-position:
      0 0,
      0 12px;
    background-size: 24px 24px;
  }

  .canvas-frame {
    position: relative;
    margin: 32px auto;
    overflow: hidden;
    box-shadow: 0 20px 60px rgb(0 0 0 / 32%);
  }

  .canvas {
    position: relative;
    overflow: hidden;
    transform-origin: left top;
  }

  :global(.boardstudio-selection-box) {
    position: absolute;
    background: rgb(78 161 255 / 6%);
    border: 1px dashed var(--el-color-primary);
  }

  :global(.boardstudio-selection-box-active) {
    outline: none;
    border: 1px dashed var(--el-color-primary);
  }
</style>
