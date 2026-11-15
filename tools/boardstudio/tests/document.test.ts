import assert from 'node:assert/strict'
import { test } from 'node:test'
import { createDocument, createNode, importLegacyLayout, validateDocument } from '../../../saiadmin-artd/src/views/plugin/boardstudio/document/model'
import { applyCommand, createDocumentSession } from '../../../saiadmin-artd/src/views/plugin/boardstudio/document/commands'
import { containerStyle, nodeStyle } from '../../../saiadmin-artd/src/views/plugin/boardstudio/document/layout'
import { operationsDocument } from '../../../saiadmin-artd/src/views/plugin/boardstudio/document/example'

test('nested operations fixture has shared grid, flex and free layout semantics', () => {
  const doc = operationsDocument()
  validateDocument(doc)
  assert.equal(containerStyle(doc.nodes.root).display, 'grid')
  assert.equal(containerStyle(doc.nodes.metrics).display, 'flex')
  assert.equal(nodeStyle(doc.nodes.status_title, doc.nodes.status).position, 'absolute')
  assert.equal(nodeStyle(doc.nodes.trend, doc.nodes.root).gridColumn, 'span 2')
  assert.equal(nodeStyle(doc.nodes.metric_0, doc.nodes.metrics).flexBasis, '0px')
})

test('commands move subtrees atomically, prevent cycles and undo whole edits', () => {
  const initial = operationsDocument()
  const session = createDocumentSession(initial)
  session.execute({type:'move', id:'status', parentId:'metrics'})
  assert.equal(session.document.nodes.metrics.children?.at(-1), 'status')
  assert.throws(() => session.execute({type:'move', id:'metrics', parentId:'status'}), /子容器/)
  assert.equal(session.document.nodes.metrics.children?.at(-1), 'status')
  assert.deepEqual(session.undo(), initial)
  session.redo()
  session.execute({type:'remove',id:'status'})
  assert.ok(!session.document.nodes.status_matrix)
  session.undo()
  assert.ok(session.document.nodes.status_matrix)
  session.execute({type:'patch',id:'metric_0',patch:{name:'新名称'}})
  assert.equal(session.canRedo,false)
  assert.equal(initial.nodes.metric_0.name,'今日订单')
})

test('duplicate remaps every descendant, shares bindings, and leaves source intact', () => {
  let doc = operationsDocument()
  doc = applyCommand(doc,{type:'duplicate',id:'status',ids:{status:'copy',status_title:'copy_title',status_matrix:'copy_matrix'}})
  assert.deepEqual(doc.nodes.copy.children,['copy_title','copy_matrix'])
  assert.equal(doc.nodes.copy.placement.x,doc.nodes.status.placement.x+24)
  assert.equal(doc.nodes.copy_title.widget?.props.text,'服务运行状态')
  assert.throws(() => applyCommand(doc,{type:'duplicate',id:'status',ids:{status:'copy'}}), /ID/)
})

test('locked ancestors block mutations, and layout conversion is one reversible command', () => {
  const session = createDocumentSession(operationsDocument())
  session.execute({type:'patch',id:'status',patch:{locked:true}})
  assert.throws(() => session.execute({type:'remove',id:'status_title'}), /锁定/)
  session.execute({type:'patch',id:'status',patch:{locked:false}})
  const before = session.document
  session.execute({type:'layout',id:'status',mode:'flex'})
  assert.equal(session.document.nodes.status.layout?.mode,'flex')
  assert.deepEqual(session.undo(),before)
})

test('document validation rejects orphan, repeated child and excessive nesting', () => {
  const orphan = createDocument()
  orphan.nodes.extra=createNode('extra','widget')
  assert.throws(()=>validateDocument(orphan),/游离/)
  const repeated=operationsDocument()
  repeated.nodes.root.children!.push('trend')
  assert.throws(()=>validateDocument(repeated),/多个容器/)
  let deep=createDocument()
  let parentId='root'
  for(let i=0;i<12;i++) {
    const node=createNode(`nested_${i}`,'container')
    deep=applyCommand(deep,{type:'insert',parentId,node})
    parentId=node.id
  }
  assert.throws(()=>applyCommand(deep,{type:'insert',parentId,node:createNode('too_deep','widget')}),/12 层/)
})

test('legacy import is explicit, nonmutating and reuses equal data bindings', () => {
  const legacy={canvas:{width:1920,height:1080},components:['a','b'].map(id=>({id,type:'stat-number',title:id,rect:{x:10,y:20,w:100,h:50,z:1},dataset:{queryTemplateId:4,params:{region:'east'},refresh:30},option:{unit:'单'}}))}
  const snapshot=JSON.stringify(legacy)
  const doc=importLegacyLayout(legacy)
  assert.equal(Object.keys(doc.bindings).length,1)
  assert.equal(doc.nodes.a.binding?.instanceId,doc.nodes.b.binding?.instanceId)
  assert.equal(doc.nodes.a.placement.x,10)
  assert.equal(JSON.stringify(legacy),snapshot)
})

test('bindings are shared by data identity and restored atomically with the component', async () => {
  const {bindingGroups}=await import('../../../saiadmin-artd/src/views/plugin/boardstudio/document/data-runtime')
  const session=createDocumentSession(operationsDocument())
  const binding={id:'b1',datasetId:9,params:{region:'east'},refresh:{mode:'interval' as const,seconds:30}}
  session.execute({type:'bind',id:'metric_0',binding,mapping:{valueField:'total'}})
  session.execute({type:'bind',id:'metric_1',binding:{...binding,id:'b2'},mapping:{valueField:'total'}})
  assert.equal(bindingGroups(session.document).size,1)
  assert.equal([...bindingGroups(session.document).values()][0].length,2)
  session.undo()
  assert.equal(session.document.nodes.metric_1.binding,undefined)
  assert.equal(session.document.bindings.b2,undefined)
})

test('page parameters resolve binding identity and parameter edits undo as one operation', async()=>{
  const {resolveDocumentBindings,resolvePageParameters}=await import('../../../saiadmin-artd/src/views/plugin/boardstudio/document/parameters')
  const session=createDocumentSession(operationsDocument())
  session.execute({type:'parameters',parameters:{customer:{type:'number',default:1}}})
  session.execute({type:'bind',id:'metric_0',binding:{id:'b1',datasetId:1,params:{customer:{pageParameter:'customer'}},refresh:{mode:'interval',seconds:30}},mapping:{valueField:'total'}})
  assert.equal(resolveDocumentBindings(session.document,{customer:'2'}).bindings.b1.params.customer,2)
  assert.throws(()=>resolvePageParameters(session.document,{customer:'1 OR 1=1'}),/数字/)
  for(const value of [true,null,' ']) assert.throws(()=>resolvePageParameters(session.document,{customer:value}),/数字/)
  assert.throws(()=>resolvePageParameters(session.document,{admin:1}),/未声明/)
  assert.throws(()=>session.execute({type:'parameters',parameters:{}}),/引用/)
  session.undo()
  assert.equal(session.document.nodes.metric_0.binding,undefined)
  session.undo()
  assert.deepEqual(session.document.parameters,{})
})

test('every widget has an editable definition and invalid property values are rejected', async()=>{
  const {widgetDefinitions,validateWidgetProperties}=await import('../../../saiadmin-artd/src/views/plugin/boardstudio/widgets/definitions')
  assert.ok(widgetDefinitions.length>=24)
  for(const definition of widgetDefinitions){
    assert.ok(definition.properties.length>0)
    validateWidgetProperties(definition.type,definition.defaults)
  }
  assert.throws(()=>validateWidgetProperties('stat-number',{decimals:99}),/不合法/)
})


test('same-container reordering accounts for removal and remains reversible',()=>{
 const session=createDocumentSession(operationsDocument())
 const before=session.document
 session.execute({type:'move',id:'metric_0',parentId:'metrics',index:2})
 assert.deepEqual(session.document.nodes.metrics.children,['metric_1','metric_0','metric_2'])
 assert.deepEqual(session.undo(),before)
})

test('runtime clears data when parameters change and skips hidden subtrees',async()=>{
 const {createDataRuntime,bindingGroups}=await import('../../../saiadmin-artd/src/views/plugin/boardstudio/document/data-runtime')
 const document=operationsDocument()
 document.bindings.b1={id:'b1',datasetId:1,params:{customer:1},refresh:{mode:'manual',seconds:30}}
 document.nodes.metric_0.binding={instanceId:'b1',mapping:{}}
 const updates:any[]=[]
 const requests:Array<{resolve:(value:any)=>void}>=[]
 const runtime=createDataRuntime(()=>new Promise(resolve=>requests.push({resolve})),(id,result)=>updates.push({id,...result}))
 runtime.replace(document)
 await new Promise(resolve=>setImmediate(resolve))
 requests[0].resolve({rows:[{total:30}]})
 await new Promise(resolve=>setImmediate(resolve))
 assert.equal(updates.at(-1).rows[0].total,30)
 document.bindings.b1.params={customer:2}
 runtime.replace(document)
 assert.deepEqual(updates.at(-1).rows,[])
 await new Promise(resolve=>setImmediate(resolve))
 requests[1].resolve({rows:[{total:50}]})
 await new Promise(resolve=>setImmediate(resolve))
 assert.equal(updates.at(-1).rows[0].total,50)
 document.nodes.metrics.hidden=true
 assert.equal(bindingGroups(document).size,0)
 runtime.stop()
})

test('all shipped presets convert to valid V2 documents and insertion undo preserves bindings',async()=>{
 const {presets}=await import('../../../saiadmin-artd/src/views/plugin/boardstudio/core/presets')
 const {presetDocument,insertPreset}=await import('../../../saiadmin-artd/src/views/plugin/boardstudio/document/presets')
 for(const preset of presets){const converted=presetDocument(preset);validateDocument(converted);assert.equal(Object.keys(converted.bindings).length,0)}
 const component=presets.find(p=>p.type==='component')!
 const session=createDocumentSession(operationsDocument()),before=session.document
 const inserted=insertPreset(before,'metrics',component,'inserted')
 assert.ok(Object.keys(inserted.nodes).length>Object.keys(before.nodes).length)
 assert.equal(inserted.nodes[inserted.nodes.metrics.children!.at(-1)!].kind,'container')
 session.execute({type:'replace',document:inserted})
 assert.deepEqual(session.undo(),before)
 assert.deepEqual(session.redo(),inserted)
 assert.throws(()=>insertPreset(inserted,'metrics',component,'inserted'),/冲突/)
})

test('multi-selection alignment is atomic and excludes selected descendants',async()=>{
 const {selectionRoots,alignSelection}=await import('../../../saiadmin-artd/src/views/plugin/boardstudio/document/selection')
 const session=createDocumentSession(operationsDocument())
 assert.deepEqual(selectionRoots(session.document,['status','status_title']),['status'])
 const before=session.document
 session.execute(alignSelection(before,['status_title','status_matrix'],'left'))
 assert.equal(session.document.nodes.status_title.placement.x,session.document.nodes.status_matrix.placement.x)
 assert.deepEqual(session.undo(),before)
 assert.throws(()=>alignSelection(before,['metric_0','metric_1'],'left'),/自由布局/)
 session.execute({type:'patch',id:'status_matrix',patch:{locked:true}})
 const locked=session.document
 assert.throws(()=>session.execute(alignSelection(locked,['status_title','status_matrix'],'right')),/锁定/)
 assert.deepEqual(session.document,locked)
})

test('batch delete and duplicate are a single undo operation',()=>{
 const session=createDocumentSession(operationsDocument()),before=session.document
 session.execute({type:'batch',commands:[{type:'remove',id:'metric_0'},{type:'remove',id:'metric_1'}]})
 assert.equal(session.document.nodes.metric_0,undefined)
 assert.equal(session.document.nodes.metric_1,undefined)
 assert.deepEqual(session.undo(),before)
 session.execute({type:'batch',commands:[{type:'duplicate',id:'metric_0',ids:{metric_0:'copy0'}},{type:'duplicate',id:'metric_1',ids:{metric_1:'copy1'}}]})
 assert.ok(session.document.nodes.copy0&&session.document.nodes.copy1)
 assert.deepEqual(session.undo(),before)
})

test('preset subtree insertion leaves unrelated locks intact and rejects orphan payloads',async()=>{
 const {presetInsertion}=await import('../../../saiadmin-artd/src/views/plugin/boardstudio/document/presets')
 const {presets}=await import('../../../saiadmin-artd/src/views/plugin/boardstudio/core/presets')
 const session=createDocumentSession(operationsDocument())
 session.execute({type:'patch',id:'metric_0',patch:{locked:true}})
 const before=session.document
 const command=presetInsertion(before,'status',presets.find(p=>p.type==='component')!,'locked_fixture')
 session.execute(command)
 assert.equal(session.document.nodes.metric_0.locked,true)
 assert.deepEqual(session.undo(),before)
 assert.throws(()=>session.execute({...command,nodes:{...command.nodes,orphan:createNode('orphan','widget')}}),/游离/)
 assert.deepEqual(session.document,before)
})

test('runtime retains transient failure data but clears revoked results and allows manual recovery',async()=>{
 const {createDataRuntime}=await import('../../../saiadmin-artd/src/views/plugin/boardstudio/document/data-runtime')
 const document=operationsDocument()
 document.bindings.b1={id:'b1',datasetId:1,params:{},refresh:{mode:'manual',seconds:30}}
 document.nodes.metric_0.binding={instanceId:'b1',mapping:{}}
 const updates:any[]=[]
 let failure:any=null
 const runtime=createDataRuntime(async()=>{if(failure)throw failure;return {rows:[{total:42}]}},(_,r)=>updates.push(r))
 const flush=()=>new Promise(r=>setImmediate(r))
 runtime.replace(document);await flush()
 failure=new Error('offline');runtime.refresh();await flush()
 assert.equal(updates.at(-1).rows[0].total,42);assert.equal(updates.at(-1).stale,true)
 failure=Object.assign(new Error('access revoked'),{code:403});runtime.refresh();await flush()
 assert.deepEqual(updates.at(-1).rows,[]);assert.equal(updates.at(-1).stale,false)
 failure=null;runtime.refresh();await flush()
 assert.equal(updates.at(-1).rows[0].total,42);assert.equal(updates.at(-1).error,'')
 runtime.stop()
})
