import React, { useEffect, useId, useState } from 'react';
import { createPortal } from 'react-dom';
import { createRoot } from 'react-dom/client';
import { useTranslation } from 'react-i18next';
import { Users, LayoutDashboard, CalendarDays, BriefcaseBusiness, Clock3, Wallet, ClipboardCheck, FileBarChart, Bell, LogOut, Building2, Menu, X, RefreshCw, ShieldCheck, UserRound, Eye, EyeOff, Trash2 } from 'lucide-react';
import mauLogo from './assets/mau-logo.jpg';
import Footer from './Footer.jsx';
import Navbar from './Navbar.jsx';
import HomePage from './HomePage.jsx';
import AboutUs from './AboutUs.jsx';
import LanguageSwitcher from './LanguageSwitcher.jsx';
import i18n from './i18n.js';
import { apiClient } from './utils/api.js';
import './style.css';
import './notifications.css';
import './login.css';

const publicRoutes = ['home', 'about', 'login', 'register'];

function readPublicRoute() {
  const route = window.location.hash.slice(1);
  return publicRoutes.includes(route) ? route : 'home';
}

function passwordPolicyKey(password){
 if([...password].length<7)return 'passwordTooShort';
 if(new TextEncoder().encode(password).length>72)return 'passwordTooLong';
 return null;
}

function usernamePolicyKey(username){
 const value=username.trim();
 if(!value)return 'usernameRequired';
 if(value.length<4)return 'usernameTooShort';
 if(value.length>80)return 'usernameTooLong';
 return null;
}

function PasswordField({label,helpText,...inputProps}){
 const {t}=useTranslation();
 const id=useId();
 const [visible,setVisible]=useState(false);
 return <div className="password-field">
   <label htmlFor={id}>{label}</label>
   <span className="password-input-wrap">
     <input {...inputProps} id={id} type={visible?'text':'password'} minLength={inputProps.minLength??7} maxLength={inputProps.maxLength??72} onInvalid={e=>{const minimum=inputProps.minLength??7;if(minimum>0&&[...e.currentTarget.value].length<minimum)e.currentTarget.setCustomValidity(t('passwordTooShort'))}} onInput={e=>e.currentTarget.setCustomValidity('')}/>
     <button className="password-toggle" type="button" onClick={()=>setVisible(value=>!value)} aria-label={t(visible?'hidePassword':'showPassword')} aria-pressed={visible} title={t(visible?'hidePassword':'showPassword')}>
       {visible?<EyeOff size={17} aria-hidden="true"/>:<Eye size={17} aria-hidden="true"/>}
     </button>
   </span>
   {helpText&&<small className="password-help">{helpText}</small>}
 </div>;
}

const request = apiClient;
const nav=[['Dashboard','dashboard',LayoutDashboard,['Administrator','HR Officer','Department Head','Employee','Management']],['Profile settings','profile-settings',UserRound,['Administrator','HR Officer','Department Head','Employee','Management']],['Employees','employees',Users,['Administrator','HR Officer']],['User accounts','user-accounts',ShieldCheck,['Administrator']],['Role requests','role-requests',ShieldCheck,['Administrator']],['Profile requests','profile-requests',ShieldCheck,['Administrator','HR Officer','Employee']],['Colleges','colleges',Building2,['Administrator']],['Departments','departments',Building2,['Administrator','HR Officer','Department Head','Management']],['Vacancies','vacancies',BriefcaseBusiness,['Administrator','HR Officer','Employee','Management']],['Leave requests','leave',CalendarDays,['Administrator','HR Officer','Department Head','Employee']],['Attendance','attendance',Clock3,['Administrator','HR Officer','Department Head','Employee','Management']],['Payroll','payroll',Wallet,['Administrator','HR Officer','Employee','Management']],['Evaluations','evaluations',ClipboardCheck,['Administrator','HR Officer','Department Head','Employee','Management']],['Reports','reports',FileBarChart,['Administrator','HR Officer','Management']],['Notifications','notifications',Bell,['Administrator','HR Officer','Department Head','Employee','Management']]];
const textKeys={
 'Dashboard':'dashboard','Profile settings':'profileSettings','Employees':'employees','Role requests':'roleRequests','Profile requests':'profileRequests','User accounts':'userAccounts','Colleges':'colleges','Departments':'departments',
 'Vacancies':'vacancies','Leave requests':'leaveRequests','Attendance':'attendance','Payroll':'payroll',
 'Evaluations':'evaluations','Reports':'reports','Notifications':'notifications',
 'Administrator':'administrator','HR Officer':'hrOfficer','Department Head':'departmentHead',
 'Employee':'employeeRole','Management':'management',
};
const fieldTextKeys={
 'Full name':'fullName','Staff number':'staffNumber','Sex':'sex','Phone':'phone','Email':'email',
 'Position':'position','College':'college','Department':'department','Hire date':'hireDate',
 'Basic salary':'basicSalary','Annual leave balance':'annualLeaveBalance','Login username':'loginUsername',
 'Initial password':'initialPassword','Account role':'accountRole','Department name':'departmentName',
 'Vacancy title':'vacancyTitle','Requirements':'requirements','Deadline':'deadline','Leave type':'leaveType',
 'Start date':'startDate','End date':'endDate','Reason':'reason','Employee ID':'employeeId','Date':'date',
 'Notes':'notes','Pay month (YYYY-MM)':'payMonth','Allowance':'allowance','Tax':'tax','Pension':'pension',
 'Other deductions':'otherDeductions','Evaluation period':'evaluationPeriod','Score (0–100)':'score','Comment':'comment',
 'College name':'collegeName','Account status':'accountStatus','Employee link':'employeeId','Requested changes':'requestedChanges',
};
const optionTextKeys={
 'Male':'male','Female':'female','Other':'other','Administrator':'administrator','HR Officer':'hrOfficer',
 'Department Head':'departmentHead','Employee':'employeeRole','Management':'management','Annual':'annual',
 'Sick':'sick','Maternity':'maternity','Paternity':'paternity','Emergency':'emergency','Unpaid':'unpaid',
 'Draft':'draft','Published':'published','Closed':'closed','Present':'present','Absent':'absent','Late':'late',
 'On Leave':'onLeave','Pending':'pending','Pending Head':'pendingHead','Pending HR':'pendingHr','Approved':'approved','Rejected':'rejected',
 'Active':'active','Inactive':'inactive',
};
const columnTextKeys={
 id:'id', emp_id:'employeeId', employee_id:'employeeId', dept_id:'department', college_id:'college',
 full_name:'fullName', employee_name:'fullName', staff_no:'staffNumber', sex:'sex',
 date_of_birth:'dateOfBirth', birth_date:'dateOfBirth', hire_date:'hireDate', salary:'basicSalary',
 basic_salary:'basicSalary', position:'position', department:'department', department_name:'departmentName',
 college:'college', college_name:'college', status:'status', type:'leaveType', leave_type:'leaveType',
 start_date:'startDate', end_date:'endDate', reason:'reason', allowance:'allowance', allowances:'allowances',
 tax:'tax', pension:'pension', deductions:'deductions', other_deductions:'otherDeductions',
 net_salary:'netSalary', pay_month:'payMonth', attendance_date:'date', date:'date', notes:'notes',
 score:'score', period:'evaluationPeriod', comment:'comment', username:'username', role:'role', email:'email',
 phone:'phone', title:'vacancyTitle', requirements:'requirements', deadline:'deadline',
 annual_leave_balance:'annualLeaveBalance', account_role:'accountRole', applications:'applicationCount',
 request_date:'date', created_at:'createdAt', decision:'status', requested_role:'requestedRole',
 changes:'requestedChanges',
};
function App(){
 const {t,i18n}=useTranslation();
 const [user,setUser]=useState(null),[page,setPage]=useState('Dashboard'),[data,setData]=useState([]),[counts,setCounts]=useState({}),[error,setError]=useState(''),[loading,setLoading]=useState(false),[mobile,setMobile]=useState(false),[search,setSearch]=useState('');
 const [profileRefresh,setProfileRefresh]=useState(0);
 const [notifications,setNotifications]=useState([]),[unreadCount,setUnreadCount]=useState(0),[notificationsOpen,setNotificationsOpen]=useState(false),[notificationsLoading,setNotificationsLoading]=useState(false),[selectedNotification,setSelectedNotification]=useState(null);
 const [username,setUsername]=useState(''),[password,setPassword]=useState(''),[loginLoading,setLoginLoading]=useState(false);
 const [publicPage,setPublicPage]=useState(readPublicRoute);
 const signup=publicPage==='register';
 const [colleges,setColleges]=useState([]);
 const [departments,setDepartments]=useState([]);
 const [regForm,setRegForm]=useState({full_name:'',username:'',password:'',role:'Employee',sex:'',birth_date:'',email:'',phone:'',position:'',college_id:'',department_id:'',staff_no:''});
 useEffect(()=>{const syncRoute=()=>setPublicPage(readPublicRoute());window.addEventListener('hashchange',syncRoute);window.addEventListener('popstate',syncRoute);return()=>{window.removeEventListener('hashchange',syncRoute);window.removeEventListener('popstate',syncRoute)}},[]);
 useEffect(()=>{const onUnauthorized=()=>{setUser(null);setPage('Dashboard')};window.addEventListener('hrms:unauthorized',onUnauthorized);return()=>window.removeEventListener('hrms:unauthorized',onUnauthorized)},[]);
 function navigatePublic(route){
   const nextRoute=publicRoutes.includes(route)?route:'home';
   if(window.location.hash!==`#${nextRoute}`)window.history.pushState(null,'',`#${nextRoute}`);
   setPublicPage(nextRoute);setError('');window.scrollTo({top:0,behavior:'smooth'});
 }
 useEffect(()=>{request('auth/me').then(x=>{if(x.user)setUser(x.user)}).catch(e=>{if(e.status!==401)setError(e.message)});},[]);
 useEffect(()=>{
   if(!user){setNotifications([]);setUnreadCount(0);setNotificationsOpen(false);setSelectedNotification(null);return}
   let cancelled=false;
   const loadNotifications=async()=>{
     setNotificationsLoading(true);
     try{
       const [items,unread]=await Promise.all([request('notifications'),request('notifications/unread-count')]);
       if(!cancelled){setNotifications(Array.isArray(items)?items:[]);setUnreadCount(Number(unread.count)||0)}
     }catch(e){if(!cancelled)setError(e.message)}
     finally{if(!cancelled)setNotificationsLoading(false)}
   };
   loadNotifications();
   const interval=window.setInterval(loadNotifications,30000);
   return()=>{cancelled=true;window.clearInterval(interval)};
 },[user?.user_id]);
 useEffect(()=>{
   if(!selectedNotification)return;
   const closeOnEscape=event=>{if(event.key==='Escape'){setSelectedNotification(null);setNotificationsOpen(false)}};
   window.addEventListener('keydown',closeOnEscape);
   return()=>window.removeEventListener('keydown',closeOnEscape);
 },[selectedNotification]);
 useEffect(()=>{if(!signup)return;let cancelled=false;request('colleges').then(x=>{if(!cancelled)setColleges(x||[])}).catch(e=>{if(!cancelled)setError(e.message)});return()=>{cancelled=true}},[signup]);
 useEffect(()=>{if(!signup||!regForm.college_id){setDepartments([]);return}let cancelled=false;request(`departments?college_id=${encodeURIComponent(regForm.college_id)}`).then(x=>{if(!cancelled)setDepartments(x||[])}).catch(e=>{if(!cancelled)setError(e.message)});return()=>{cancelled=true}},[signup,regForm.college_id]);
 useEffect(()=>{if(user)loadPage(page)},[user,page]);
 async function loadPage(p){if(p==='Profile settings'){setError('');return}setLoading(true);setError('');try{if(p==='Dashboard'){const r=await request('dashboard');setCounts(r.counts);setData([])}else{let endpoint=p.toLowerCase();if(p==='Leave requests')endpoint='leave';if(p==='Role requests')endpoint='role-requests';if(p==='Profile requests')endpoint='profile-requests';if(p==='User accounts')endpoint='users';if(p==='Reports'){setData([await request('reports')]);}else{const r=await request(endpoint+(p==='Vacancies'&&user.role==='Employee'?'/public':'' )+(p==='Employees'&&search?'?q='+encodeURIComponent(search):''));setData(Array.isArray(r)?r:(r?[r]:[]));}}}catch(e){setError(e.message);setData([])}finally{setLoading(false)}}
 async function login(e){e.preventDefault();setError('');const usernameError=usernamePolicyKey(username);if(usernameError){setError(t(usernameError));return}setLoginLoading(true);try{const r=await request('auth/login',{method:'POST',body:JSON.stringify({username,password})});if(r.token)window.localStorage.setItem('access_token',r.token);setUser(r.user);setPage('Dashboard')}catch(e){setError(e.status===401?t('invalid_credentials'):e.message)}finally{setLoginLoading(false)}}
 async function registerEmployee(e){e.preventDefault();setError('');const usernameError=usernamePolicyKey(regForm.username);if(usernameError){setError(t(usernameError));return}const passwordError=passwordPolicyKey(regForm.password);if(passwordError){setError(t(passwordError));return}try{const result=await request('auth/register',{method:'POST',body:JSON.stringify(regForm)});setRegForm({full_name:'',username:'',password:'',role:'Employee',sex:'',birth_date:'',email:'',phone:'',position:'',college_id:'',department_id:'',staff_no:''});navigatePublic('login');setError(result.message);}catch(e){setError(e.status===409?t('user_already_exists'):e.message)}}
 async function logout(){
   let logoutError='';
   try{await request('auth/logout',{method:'POST',body:'{}'})}catch(e){logoutError=e.message}
   setUser(null);
   setPassword('');
   navigatePublic('login');
   if(logoutError)setError(logoutError);
 }
 async function markNotificationRead(notificationId){
   try{
     await request(`notifications/${notificationId}`,{method:'PATCH',body:'{}'});
     setNotifications(current=>current.map(item=>Number(item.not_id)===notificationId?{...item,is_read:1}:item));
     setUnreadCount(count=>Math.max(0,count-1));
   }catch(e){setError(e.message)}
 }
 async function deleteNotification(notification){
   if(!window.confirm(t('confirmDeleteNotification')))return;
   try{
     await request(`notifications/${notification.not_id}`,{method:'DELETE'});
     setNotifications(current=>current.filter(item=>Number(item.not_id)!==Number(notification.not_id)));
     if(Number(notification.is_read)===0)setUnreadCount(count=>Math.max(0,count-1));
     if(Number(selectedNotification?.not_id)===Number(notification.not_id))setSelectedNotification(null);
   }catch(e){setError(e.message)}
 }
 function openNotification(notification){
   setSelectedNotification({...notification,details:{
     event_type:notification.event_type,
     created_at:notification.created_at,
     ...(notification.details||{}),
   }});
   setNotificationsOpen(true);
   if(Number(notification.is_read)===0)markNotificationRead(Number(notification.not_id));
 }
 async function markAllNotificationsRead(){
   try{
     await request('notifications/read-all',{method:'PATCH',body:'{}'});
     setNotifications(current=>current.map(item=>({...item,is_read:1})));
     setUnreadCount(0);
   }catch(e){setError(e.message)}
 }
 function notificationDetailLabel(key){
   const labels={event_type:t('eventType'),created_at:t('createdAt'),request_type:t('requestType'),request_id:t('requestId'),leave_type:t('leaveType'),status:t('status'),start_date:t('startDate'),end_date:t('endDate'),days:t('days'),reason:t('reason'),sender:t('sender'),reviewed_by:t('reviewedBy'),requested_role:t('requestedRole'),date:t('date'),action:t('action'),comment:t('comment'),changes:t('requestedChanges')};
   return labels[key]||key.replaceAll('_',' ').replace(/\b\w/g,letter=>letter.toUpperCase());
 }
 function notificationDetailValue(key,value){
   if(value===null||value===undefined||value==='')return '—';
   if(key==='status'&&optionTextKeys[String(value)])return t(optionTextKeys[String(value)]);
   if(typeof value==='object')return JSON.stringify(value,null,2);
   if(key==='date'||key==='created_at'){const parsed=new Date(value);if(!Number.isNaN(parsed.getTime()))return parsed.toLocaleString(i18n.language)}
   if(key==='event_type')return String(value).replaceAll('_',' ').replace(/\b\w/g,letter=>letter.toUpperCase());
   return String(value);
 }
 if(!user)return (
   <div className="public-site" lang={i18n.resolvedLanguage||i18n.language}>
    <Navbar currentPage={publicPage} onNavigate={navigatePublic}/>
    {(publicPage==='home'||publicPage==='about')&&error&&<div className="alert public-alert" role="alert">{error}</div>}
    {publicPage==='home'?<HomePage onNavigate={navigatePublic}/>:publicPage==='about'?<AboutUs/>:<div className="login-shell"><div className="login-layout"><div className="login-card">
     <div className="login-language-switcher"><LanguageSwitcher /></div>
     {signup ? <form onSubmit={registerEmployee}>
       <div className="login-brand"><img className="university-logo university-logo--login" src={mauLogo} alt={t('institutionName')} /><p className="eyebrow">{t('universityName')}</p></div>
       <h1>{t('newEmployeeAccount')}</h1><p className="muted">{t('createStaffLogin')}</p>
       {error&&<div className="alert" role="alert">{error}</div>}
       <label>{t('fullName')}<input value={regForm.full_name} onChange={e=>setRegForm({...regForm,full_name:e.target.value})} required/></label>
       <label>{t('username')}<input value={regForm.username} onChange={e=>setRegForm({...regForm,username:e.target.value})} minLength="4" maxLength="80" onInvalid={e=>{if(e.currentTarget.value.length<4)e.currentTarget.setCustomValidity(t(e.currentTarget.value.length?'usernameTooShort':'usernameRequired'))}} onInput={e=>e.currentTarget.setCustomValidity('')} required/></label>
       <PasswordField label={t('password')} helpText={t('passwordRequirementsHint')} value={regForm.password} onChange={e=>setRegForm({...regForm,password:e.target.value})} maxLength="72" required/>
       <label>{t('sex')}<select value={regForm.sex} onChange={e=>setRegForm({...regForm,sex:e.target.value})}>
         <option value="">{t('select')}</option>
         {['Male','Female','Other'].map(sex=><option key={sex} value={sex}>{t(sex.toLowerCase())}</option>)}
       </select></label>
       <label>{t('dateOfBirth')}<input type="date" value={regForm.birth_date} onChange={e=>setRegForm({...regForm,birth_date:e.target.value})}/></label>
       <label>{t('requestedRole')}<select value={regForm.role} onChange={e=>setRegForm({...regForm,role:e.target.value})} required>{['Employee','HR Officer','Department Head','Management','Administrator'].map(role=><option key={role} value={role}>{t(textKeys[role])}</option>)}</select></label>
       <p className="small muted">{t('roleApprovalNotice')}</p>
       <label>{t('college')}<select value={regForm.college_id} onChange={e=>setRegForm({...regForm,college_id:e.target.value,department_id:''})} required>
         <option value="">{t('selectCollege')}</option>{colleges.map(college=><option key={college.college_id} value={college.college_id}>{college.name}</option>)}
       </select></label>
       <label>{t('department')}<select value={regForm.department_id} onChange={e=>setRegForm({...regForm,department_id:e.target.value})} disabled={!regForm.college_id} required>
         <option value="">{t('selectDepartment')}</option>{departments.map(department=><option key={department.department_id} value={department.department_id}>{department.name}</option>)}
       </select></label>
       <label>{t('staffNumber')}<input value={regForm.staff_no} onChange={e=>setRegForm({...regForm,staff_no:e.target.value})}/></label>
       <label>{t('email')}<input type="email" value={regForm.email} onChange={e=>setRegForm({...regForm,email:e.target.value})}/></label>
       <label>{t('phone')}<input value={regForm.phone} onChange={e=>setRegForm({...regForm,phone:e.target.value})}/></label>
       <label>{t('position')}<input value={regForm.position} onChange={e=>setRegForm({...regForm,position:e.target.value})}/></label>
       <button className="primary full" type="submit">{t('createAccount')}</button>
       <button className="secondary full" type="button" onClick={()=>navigatePublic('login')}>{t('backToSignIn')}</button>
     </form> : <form onSubmit={login}>
       <div className="login-brand"><img className="university-logo university-logo--login" src={mauLogo} alt={t('institutionName')} /><p className="eyebrow">{t('universityName')}</p></div>
       <h1>{t('hrmsTitle')}</h1><p className="muted">{t('signInDescription')}</p>
       {error&&<div className="alert" role="alert">{error}</div>}
       <label>{t('username')}<input value={username} onChange={e=>setUsername(e.target.value)} autoComplete="username" minLength="4" maxLength="80" onInvalid={e=>{if(e.currentTarget.value.length<4)e.currentTarget.setCustomValidity(t(e.currentTarget.value.length?'usernameTooShort':'usernameRequired'))}} onInput={e=>e.currentTarget.setCustomValidity('')} required/></label>
       <PasswordField label={t('password')} value={password} onChange={e=>setPassword(e.target.value)} autoComplete="current-password" minLength={0} required/>
      <button className="primary full" type="submit" disabled={loginLoading} aria-busy={loginLoading}>{t('signIn')}</button>
       <button className="secondary full" type="button" onClick={()=>navigatePublic('register')}>{t('createAccount')}</button>
       <p className="small muted">{t('adminAccountNotice')}</p>
     </form>}
   </div><aside className="campus-panel" lang={i18n.resolvedLanguage || i18n.language}><div className="campus-copy"><span className="eyebrow">{t('universityName')}</span><h2>{t('campusTitle')}</h2><p>{t('campusAddress')}</p></div><div className="campus-map"><iframe title={t('campusLocation')} src="https://maps.google.com/maps?q=Mekdela%20Amba%20University%20Tulu%20Awulia%20Main%20Campus&output=embed" loading="lazy" referrerPolicy="no-referrer-when-downgrade" allowFullScreen/></div><a className="map-link" href="https://www.google.com/maps/search/?api=1&query=Mekdela+Amba+University+Tulu+Awulia+Main+Campus" target="_blank" rel="noreferrer">{t('openMaps')}</a><p className="background-credit">Background photo (cropped): <a href="https://commons.wikimedia.org/wiki/File:Addis_Ababa_University_gate.jpg" target="_blank" rel="noreferrer">Radosław Botev / Wikimedia Commons</a> · <a href="https://creativecommons.org/licenses/by/3.0/pl/" target="_blank" rel="noreferrer">CC BY 3.0 PL</a></p></aside></div><Footer /></div>}
    {publicPage==='home'||publicPage==='about'?<Footer/>:null}
   </div>
 );
 const visible=nav.filter(n=>n[3].includes(user.role));
 return <div className="app-shell"><aside className={`sidebar ${mobile?'open':''}`}><div className="brand"><img className="university-logo university-logo--sidebar" src={mauLogo} alt={t('institutionName')} /><div><strong>MAU HRMS</strong><small>{t('hrmsTitle')}</small></div><button className="icon-btn mobile-close" onClick={()=>setMobile(false)} aria-label={t('cancel')}><X size={18}/></button></div><nav className="sidebar-nav" aria-label={t('workspace')}><div className="nav-label">{t('workspace')}</div>{visible.map(([label,key,Icon])=><button key={key} className={`nav-item ${page===label?'active':''}`} onClick={()=>{setPage(label);setMobile(false)}}><Icon size={18}/><span>{t(textKeys[label])}</span></button>)}</nav><div className="sidebar-bottom"><div className="user-chip"><div className="avatar">{(user.full_name||user.username).slice(0,1).toUpperCase()}</div><div><strong>{user.full_name||user.username}</strong><small>{t(textKeys[user.role])||user.role}</small></div></div></div></aside><main className="main"><header className="topbar"><button className="icon-btn mobile-menu" onClick={()=>setMobile(true)} aria-label={t('workspace')}><Menu/></button><div><p className="eyebrow">{t('hrAdministration')}</p><h2>{t(textKeys[page])||page}</h2></div><div className="top-actions"><div className="notification-menu"><button className="icon-btn notification-trigger" type="button" aria-label={t('notifications')} aria-expanded={notificationsOpen} aria-haspopup="true" onClick={()=>setNotificationsOpen(open=>!open)}><Bell size={18}/>{unreadCount>0&&<span className="notification-badge">{unreadCount>99?'99+':unreadCount}</span>}</button>{notificationsOpen&&<div className="notification-dropdown" role="dialog" aria-label={t('notifications')}><div className="notification-dropdown__header"><strong>{t('notifications')}</strong>{unreadCount>0&&<button type="button" className="notification-mark-all" onClick={markAllNotificationsRead}>{t('markAllRead')}</button>}</div>{notificationsLoading?<p className="notification-empty">{t('loadingRecords')}</p>:notifications.length===0?<p className="notification-empty">{t('noNotifications')}</p>:<div className="notification-dropdown__list" onClick={event=>{if(event.target.closest('button:not(.notification-item__open)'))return;const row=event.target.closest('.notification-item');if(!row)return;const index=Array.prototype.indexOf.call(event.currentTarget.children,row);if(notifications[index])openNotification(notifications[index])}}>{notifications.map(item=><div className={`notification-item ${Number(item.is_read)===0?'unread':''}`} key={item.not_id}><button type="button" className="notification-item__open"><strong>{item.title||item.event_type||t('notifications')}</strong><p>{item.message}</p><small>{new Date(item.created_at).toLocaleString(i18n.language)}</small></button>{Number(item.is_read)===0&&<button type="button" className="notification-mark-read" onClick={()=>markNotificationRead(Number(item.not_id))}>{t('markRead')}</button>}<button type="button" className="notification-delete" aria-label={t('deleteNotification')} title={t('deleteNotification')} onClick={()=>deleteNotification(item)}><Trash2 size={15}/></button></div>)}</div>}{selectedNotification&&createPortal(<div className="notification-modal-backdrop" onMouseDown={event=>{if(event.target===event.currentTarget){setSelectedNotification(null);setNotificationsOpen(false)}}}><section className="notification-modal" role="dialog" aria-modal="true" aria-labelledby="notification-modal-title"><header className="notification-modal__header"><h3 id="notification-modal-title">{selectedNotification.title}</h3><button type="button" className="icon-btn" aria-label={t('close')} onClick={()=>{setSelectedNotification(null);setNotificationsOpen(false)}}><X size={18}/></button></header><p className="notification-modal__message">{selectedNotification.message}</p><dl className="notification-modal__details">{Object.entries(selectedNotification.details||{}).map(([key,value])=><div className="notification-detail" key={key}><dt>{notificationDetailLabel(key)}</dt><dd>{notificationDetailValue(key,value)}</dd></div>)}</dl></section></div>,document.body)}</div>}</div><LanguageSwitcher/><span className="role-pill">{t(textKeys[user.role])||user.role}</span><button className="icon-btn" title={t('refresh')} onClick={()=>page==='Profile settings'?setProfileRefresh(value=>value+1):loadPage(page)}><RefreshCw size={17}/></button><button className="nav-item logout" onClick={logout} aria-label={t('signOut')} title={t('signOut')}><LogOut size={18}/><span>{t('signOut')}</span></button></div></header><section className="content">{error&&<div className="alert" role="alert">{error}</div>}{page==='Dashboard'?<Dashboard counts={counts} user={user} onNavigate={setPage}/>:page==='Profile settings'?<ProfileSettings refreshToken={profileRefresh} onProfileUpdated={profile=>setUser(current=>({...current,username:profile.username,full_name:profile.full_name}))}/>:<DataPage page={page} rows={data} user={user} loading={loading} search={search} setSearch={setSearch} refresh={()=>loadPage(page)} setError={setError}/>}</section><Footer /></main></div>
}
function Dashboard({counts,user,onNavigate}){const {t}=useTranslation();const cards=[['Employees',counts.employees??0,Users],['Departments',counts.departments??0,Building2],['Vacancies',counts.vacancies??0,BriefcaseBusiness],['Pending leave',counts.pending_leave??0,CalendarDays]].filter(([label])=>user.role!=='Employee'||!['Employees','Departments'].includes(label));return <><div className="welcome"><div><span className="eyebrow">{t('overview')}</span><h1>{t('welcomeBack',{name:(user.full_name||user.username).split(' ')[0]})}</h1><p>{t('summaryWelcome')}</p></div><div className="welcome-icon"><LayoutDashboard size={32}/></div></div><div className="stat-grid">{cards.map(([label,value,Icon])=><button className="stat-card" key={label} onClick={()=>onNavigate(label==='Pending leave'?'Leave requests':label)}><div className="stat-top"><span>{label==='Pending leave'?t('pendingLeave'):t(textKeys[label])}</span><Icon size={19}/></div><strong>{value}</strong><small>{t('currentRecords')}</small></button>)}</div><div className="panel"><div className="panel-heading"><div><h3>{t('hrmsModules')}</h3><p>{t('moduleDescription')}</p></div></div><div className="module-grid">{nav.filter(n=>n[3].includes(user.role)&&n[1]!=='dashboard').slice(0,8).map(([label,key,Icon])=><button key={key} className="module-card" onClick={()=>onNavigate(label)}><span className="module-icon"><Icon size={20}/></span><span><strong>{t(textKeys[label])}</strong><small>{t('openModule')}</small></span><span className="arrow">↗</span></button>)}</div></div></>}
function ProfileSettings({refreshToken,onProfileUpdated}){
 const {t}=useTranslation();
 const [profile,setProfile]=useState(null),[loading,setLoading]=useState(true),[profileBusy,setProfileBusy]=useState(false),[passwordBusy,setPasswordBusy]=useState(false);
 const [profileError,setProfileError]=useState(''),[profileSuccess,setProfileSuccess]=useState('');
 const [passwordError,setPasswordError]=useState(''),[passwordSuccess,setPasswordSuccess]=useState('');
 const [passwordForm,setPasswordForm]=useState({current_password:'',new_password:'',confirm_password:''});
 useEffect(()=>{
   let cancelled=false;
   request('profile').then(result=>{if(!cancelled)setProfile(result)}).catch(error=>{if(!cancelled)setProfileError(error.message)}).finally(()=>{if(!cancelled)setLoading(false)});
   return()=>{cancelled=true};
 },[refreshToken]);
 async function saveProfile(e){
   e.preventDefault();setProfileError('');setProfileSuccess('');
   const usernameError=usernamePolicyKey(profile.username||'');
   if(usernameError){setProfileError(t(usernameError));return}
   setProfileBusy(true);
   try{
     const updated=await request('profile',{method:'PATCH',body:JSON.stringify(profile)});
     setProfile(updated);onProfileUpdated(updated);setProfileSuccess(t('profileUpdated'));
   }catch(error){setProfileError(error.message)}finally{setProfileBusy(false)}
 }
 async function changePassword(e){
   e.preventDefault();setPasswordError('');setPasswordSuccess('');
   const passwordErrorKey=passwordPolicyKey(passwordForm.new_password);
   if(passwordErrorKey){setPasswordError(t(passwordErrorKey));return}
   if(passwordForm.new_password!==passwordForm.confirm_password){setPasswordError(t('passwordMismatch'));return}
   setPasswordBusy(true);
   try{
     await request('change-password',{method:'POST',body:JSON.stringify(passwordForm)});
     setPasswordForm({current_password:'',new_password:'',confirm_password:''});setPasswordSuccess(t('passwordUpdated'));
   }catch(error){setPasswordError(error.message)}finally{setPasswordBusy(false)}
 }
 if(loading)return <div className="empty"><RefreshCw className="spin"/> {t('loadingRecords')}</div>;
 if(!profile)return <div className="panel"><div className="alert" role="alert">{profileError}</div></div>;
 return <div className="profile-settings">
   <section className="panel">
     <div className="panel-heading"><div><h3>{t('profileDetails')}</h3><p>{t('profileDescription')}</p></div></div>
     {profileError&&<div className="alert" role="alert">{profileError}</div>}
     {profileSuccess&&<div className="alert alert-success" role="status">{profileSuccess}</div>}
     <form className="profile-form" onSubmit={saveProfile}>
       <label>{t('username')}<input value={profile.username||''} onChange={e=>setProfile({...profile,username:e.target.value})} minLength="4" maxLength="80" onInvalid={e=>{if(e.currentTarget.value.length<4)e.currentTarget.setCustomValidity(t(e.currentTarget.value.length?'usernameTooShort':'usernameRequired'))}} onInput={e=>e.currentTarget.setCustomValidity('')} required/></label>
       {profile.emp_id&&<>
         <label>{t('fullName')}<input value={profile.full_name||''} onChange={e=>setProfile({...profile,full_name:e.target.value})} maxLength="160" required/></label>
         <label>{t('phone')}<input type="tel" value={profile.phone||''} onChange={e=>setProfile({...profile,phone:e.target.value})} maxLength="40"/></label>
         <label>{t('email')}<input type="email" value={profile.email||''} onChange={e=>setProfile({...profile,email:e.target.value})} maxLength="160"/></label>
         <label>{t('sex')}<select value={profile.sex||''} onChange={e=>setProfile({...profile,sex:e.target.value})}>
           <option value="">{t('select')}</option>
           {['Male','Female','Other'].map(sex=><option key={sex} value={sex}>{t(sex.toLowerCase())}</option>)}
         </select></label>
         <label>{t('dateOfBirth')}<input type="date" value={profile.birth_date||''} onChange={e=>setProfile({...profile,birth_date:e.target.value})}/></label>
       </>}
       <div className="profile-form__actions"><button className="primary" type="submit" disabled={profileBusy}>{profileBusy?t('saving'):t('saveProfile')}</button></div>
     </form>
   </section>
   <section className="panel">
     <div className="panel-heading"><div><h3>{t('changePassword')}</h3></div></div>
     {passwordError&&<div className="alert" role="alert">{passwordError}</div>}
     {passwordSuccess&&<div className="alert alert-success" role="status">{passwordSuccess}</div>}
     <form className="profile-form" onSubmit={changePassword}>
       <PasswordField label={t('currentPassword')} autoComplete="current-password" minLength={0} value={passwordForm.current_password} onChange={e=>setPasswordForm({...passwordForm,current_password:e.target.value})} required/>
       <PasswordField label={t('newPassword')} helpText={t('passwordRequirementsHint')} autoComplete="new-password" maxLength="72" value={passwordForm.new_password} onChange={e=>setPasswordForm({...passwordForm,new_password:e.target.value})} required/>
       <PasswordField label={t('confirmNewPassword')} autoComplete="new-password" maxLength="72" value={passwordForm.confirm_password} onChange={e=>setPasswordForm({...passwordForm,confirm_password:e.target.value})} required/>
       <div className="profile-form__actions"><button className="primary" type="submit" disabled={passwordBusy}>{passwordBusy?t('saving'):t('updatePassword')}</button></div>
     </form>
   </section>
 </div>;
}
function DataPage({page,rows,user,loading,search,setSearch,refresh,setError}){
 const {t}=useTranslation();
 const [showForm,setShowForm]=useState(false),[form,setForm]=useState({}),[busy,setBusy]=useState(false),[editingId,setEditingId]=useState(null);
 const [success,setSuccess]=useState('');
 const [showLinkForm,setShowLinkForm]=useState(false),[linkForm,setLinkForm]=useState({emp_id:'',username:'',password:'',role:'Employee'}),[linkBusy,setLinkBusy]=useState(false);
 const [staffFilter,setStaffFilter]=useState('');
 const [collegeOptions,setCollegeOptions]=useState([]),[departmentOptions,setDepartmentOptions]=useState([]),[staffOptions,setStaffOptions]=useState([]),[notificationUsers,setNotificationUsers]=useState([]);
 useEffect(()=>{if(!['Employees','Departments','Profile requests','User accounts','Payroll','Evaluations'].includes(page))return;let cancelled=false;if(['Employees','Departments','Profile requests','User accounts'].includes(page))request('colleges').then(result=>{if(!cancelled)setCollegeOptions(result)}).catch(e=>{if(!cancelled)setError(e.message)});const canLoadEmployees=(page==='User accounts'&&user.role==='Administrator')||(page==='Payroll'&&['Administrator','HR Officer'].includes(user.role))||(page==='Evaluations'&&['Administrator','Department Head'].includes(user.role));if(canLoadEmployees){const endpoint=page==='Evaluations'?'evaluations/employees':'employees';request(endpoint).then(result=>{if(!cancelled)setStaffOptions(Array.isArray(result)?result:[])}).catch(e=>{if(!cancelled)setError(e.message)})}return()=>{cancelled=true}},[page,user.role,setError]);
 useEffect(()=>{if(page!=='Notifications'||!['Administrator','HR Officer'].includes(user?.role))return;let cancelled=false;request('users').then(result=>{if(!cancelled)setNotificationUsers(Array.isArray(result)?result:[])}).catch(e=>{if(!cancelled)setError(e.message)});return()=>{cancelled=true}},[page,user?.role,setError]);
 useEffect(()=>{if(!form.college_id){setDepartmentOptions([]);return}let cancelled=false;request(`departments?college_id=${encodeURIComponent(form.college_id)}`).then(result=>{if(!cancelled)setDepartmentOptions(result)}).catch(e=>{if(!cancelled)setError(e.message)});return()=>{cancelled=true}},[form.college_id,setError]);
 const definitions={Employees:[['full_name','Full name','text',null,true],['staff_no','Staff number','text'],['sex','Sex','select',['Male','Female','Other']],['phone','Phone','text'],['email','Email','email'],['position','Position','text'],['college_id','College','college',null,true],['department_id','Department','department',null,true],['hire_date','Hire date','date'],['salary','Basic salary','number'],['annual_leave_balance','Annual leave balance','number'],['account_username','Login username','text'],['account_password','Initial password','password'],['account_role','Account role','select',['Administrator','HR Officer','Department Head','Employee','Management']]],Departments:[['name','Department name','text',null,true],['college_id','College','college',null,true]],Colleges:[['name','College name','text',null,true]],'User accounts':[['username','Username','text',null,true],['email','Email','email'],['password','Initial password','password'],['role','Account role','select',['Administrator','HR Officer','Department Head','Employee','Management'],true],['status','Account status','select',['Active','Inactive'],true],['emp_id','Employee link','employee']], 'Profile requests':[['full_name','Full name','text'],['phone','Phone','text'],['email','Email','email'],['position','Position','text'],['college_id','College','college'],['department_id','Department','department']],Vacancies:[['title','Vacancy title','text',null,true],['requirements','Requirements','textarea',null,true],['deadline','Deadline','date',null,true],['status','Status','select',['Draft','Published','Closed']]],'Leave requests':[['type','Leave type','select',['Annual','Sick','Maternity','Paternity','Emergency','Unpaid','Other'],true],['start_date','Start date','date',null,true],['end_date','End date','date',null,true],['reason','Reason','textarea',null,true]],Attendance:[['emp_id','Employee ID','number',null,true],['attendance_date','Date','date',null,true],['status','Status','select',['Present','Absent','Late','On Leave'],true],['notes','Notes','text']],Payroll:[['emp_id','Employee ID','number',null,true],['pay_month','Pay month (YYYY-MM)','month',null,true],['allowance','Allowance','number'],['tax','Tax','number'],['pension','Pension','number'],['other_deductions','Other deductions','number']],Evaluations:[['emp_id','Employee ID','number',null,true],['period','Evaluation period','text',null,true],['score','Score (0–100)','number',null,true],['comment','Comment','textarea']]};
 if(page==='User accounts')definitions['User accounts'].splice(2,0,['phone','Phone','tel']);
 const canCreate=({Employees:['Administrator','HR Officer'],Departments:['Administrator','HR Officer'],Colleges:['Administrator'],'User accounts':['Administrator'],'Profile requests':user.role==='Employee'&&user.emp_id?['Employee']:[],Vacancies:['Administrator','HR Officer'],'Leave requests':['Employee'],Attendance:['Administrator','HR Officer'],Payroll:['Administrator','HR Officer'],Evaluations:['Administrator','Department Head'],'Notifications':['Administrator','HR Officer']})[page]?.includes(user.role);
 const canManageNotifications=page==='Notifications'&&['Administrator','HR Officer'].includes(user.role);
 const createLabel=page==='User accounts'?t('createAccountRecord'):page==='Profile requests'?t('sendRequest'):t(`add${page==='Leave requests'?'Request':page==='Employees'?'Employee':page==='Vacancies'?'Vacancy':page==='Departments'?'Department':page==='Colleges'?'College':page==='Attendance'?'Record':page==='Payroll'?'Payroll':'Evaluation'}`);
 const endpoint=page==='Leave requests'?'leave':page==='Role requests'?'role-requests':page==='Profile requests'?'profile-requests':page==='User accounts'?'users':page.toLowerCase();
 const columns=rows.length&&rows[0]&&!Array.isArray(rows[0])?Object.keys(rows[0]).filter(k=>!['password_hash','cv_path','head_comment','hr_comment','created_by','created_at','updated_at','user_id','request_id','not_id','is_read','reviewed_by','reviewed_at','changes_json'].includes(k)).slice(0,7):[];
 async function submit(e){
   e.preventDefault();setError('');setSuccess('');
   try{
     let payload={...form};
     if(page==='User accounts'){const usernameError=usernamePolicyKey(payload.username||'');if(usernameError)throw new Error(t(usernameError))}
     if(page==='Vacancies'&&!payload.status)payload.status='Draft';
     if(page==='Profile requests'&&!editingId&&!Object.values(payload).some(value=>String(value??'').trim()!==''))throw new Error(t('profileRequestRequired'));
     if(page==='User accounts'&&!editingId&&!payload.password)throw new Error(t('passwordRequired'));
     if(page==='User accounts'&&payload.password){const passwordError=passwordPolicyKey(payload.password);if(passwordError)throw new Error(t(passwordError))}
     if(page==='Employees'){
       const hasUsername=Boolean(payload.account_username?.trim()),hasPassword=Boolean(payload.account_password);
       if(hasUsername!==hasPassword)throw new Error(t('enterLoginCredentials'));
       if(!hasUsername){delete payload.account_username;delete payload.account_password;delete payload.account_role;}
       else{const usernameError=usernamePolicyKey(payload.account_username);if(usernameError)throw new Error(t(usernameError));const passwordError=passwordPolicyKey(payload.account_password);if(passwordError)throw new Error(t(passwordError))}
     }
     if(page==='Profile requests')payload={changes:payload};
     if(editingId&&page==='User accounts'&&!payload.password)delete payload.password;
     setBusy(true);
     const method=editingId?(page==='User accounts'?'PATCH':'PUT'):'POST';
     const result=await request(`${endpoint}${editingId?`/${editingId}`:''}`,{method,body:JSON.stringify(payload)});
     setForm({});setShowForm(false);setEditingId(null);await refresh();
     if(!editingId&&['Profile requests','Leave requests'].includes(page))setSuccess(t(result.email_notification_sent||result.sms_notification_sent?'requestSubmittedDeliverySent':'requestSubmittedDeliveryFailed'));
   }catch(e){setError(e.message)}finally{setBusy(false)}
 }
 async function submitLinkedAccount(e){e.preventDefault();setError('');const usernameError=usernamePolicyKey(linkForm.username);if(usernameError){setError(t(usernameError));return}const passwordError=passwordPolicyKey(linkForm.password);if(passwordError){setError(t(passwordError));return}setLinkBusy(true);try{await request('employee-accounts',{method:'POST',body:JSON.stringify(linkForm)});setLinkForm({emp_id:'',username:'',password:'',role:'Employee'});setShowLinkForm(false);setError(t('accountCreated'));refresh()}catch(e){setError(e.message)}finally{setLinkBusy(false)}}
 async function decision(id,decision){const comment=prompt(t('optionalComment'))||'';try{await request(`leave/${id}`,{method:'PATCH',body:JSON.stringify({decision,comment})});refresh()}catch(e){setError(e.message)}}
 async function updateStatus(id,status){try{await request(`vacancies/${id}`,{method:'PATCH',body:JSON.stringify({status})});refresh()}catch(e){setError(e.message)}}
 async function reviewRoleRequest(id,decision){try{await request(`role-requests/${id}`,{method:'PATCH',body:JSON.stringify({decision})});refresh()}catch(e){setError(e.message)}}
 async function reviewProfileRequest(id,decision){try{await request(`profile-requests/${id}`,{method:'PATCH',body:JSON.stringify({decision})});refresh()}catch(e){setError(e.message)}}
 function recordId(row){if(page==='Notifications')return row.not_id??row.id??row.notification_id;return page==='Employees'?row.emp_id:page==='Departments'?row.dept_id:page==='Colleges'?row.college_id:row.user_id}
 function startEdit(row){const id=recordId(row);if(page==='Notifications'){setEditingId(id);setForm({user_id:String(row.user_id??''),title:row.title??'',message:row.message??'',type:row.event_type??row.type??'system',details:row.details&&Object.keys(row.details).length?JSON.stringify(row.details,null,2):''});setShowForm(true);setShowLinkForm(false);return}const values={...row,password:'',account_username:'',account_password:'',account_role:'Employee'};if(page==='Employees'){values.department_id=row.dept_id??row.department_id??'';delete values.dept_id;}setEditingId(id);setForm(values);setShowForm(true);setShowLinkForm(false)}
 async function removeRecord(row){const id=recordId(row);if(!id||!window.confirm(t('confirmDelete')))return;try{await request(`${endpoint}/${id}`,{method:'DELETE'});refresh()}catch(e){setError(e.message)}}
 async function submitNotification(e){
   e.preventDefault();setError('');setSuccess('');
   try {
     const userId=Number(form.user_id);
     const message=(form.message||'').trim();
     if(!userId||userId<1)throw new Error('Select a recipient');
     if(!message)throw new Error('Notification message is required');
     let details={};
     if(form.details&&form.details.trim()){
       try { const parsed=JSON.parse(form.details); if(parsed!==null && typeof parsed==='object' && !Array.isArray(parsed)) details=parsed; else throw new Error(''); }
       catch{ throw new Error('Details must be valid JSON object'); }
     }
     const payload={user_id:userId,title:(form.title||'MAU HRMS notification').trim()||'MAU HRMS notification',message,type:(form.type||'system').trim()||'system',details};
     const result=await request(editingId?`notifications/${editingId}`:'notifications',{method:editingId?'PUT':'POST',body:JSON.stringify(payload)});
     setForm({});setShowForm(false);setEditingId(null);if(!editingId)setSuccess('Notification created');else setSuccess('Notification updated');await refresh();
     if(result&&result.notification_id) setSuccess('Notification created');
   }catch(e){setError(e.message)}
 }
 const actionable=page==='Leave requests'&&['Administrator','Department Head','HR Officer'].includes(user.role);
 const roleRequests=page==='Role requests';
 const profileActionable=page==='Profile requests'&&['Administrator','HR Officer'].includes(user.role);
 const canManage=page==='Employees'?user.role==='Administrator':['Colleges','Departments','User accounts'].includes(page)&&user.role==='Administrator';
 const visibleRows=page==='Employees'?rows.filter(employee=>!staffFilter||String(employee.emp_id)===staffFilter):rows;
 function columnTitle(column){if(column==='name'&&page==='Colleges')return t('collegeName');if(column==='name'&&page==='Departments')return t('departmentName');if(column==='college_id'&&page==='Colleges')return t('id');return t(columnTextKeys[column]||fieldTextKeys[column]||column.replaceAll('_',' '))}
 function displayValue(column,value){if(value===null||value===undefined)return '—';if(column==='status')return t(optionTextKeys[value]||value);if(column==='college_id'){const college=collegeOptions.find(option=>String(option.college_id)===String(value));if(college)return college.name}if(column==='changes'&&typeof value==='object')return Object.entries(value).map(([key,item])=>`${t(columnTextKeys[key]||key)}: ${optionTextKeys[String(item)]?t(optionTextKeys[String(item)]):item}`).join(' · ');if(typeof value==='object')return JSON.stringify(value);if(optionTextKeys[String(value)])return t(optionTextKeys[String(value)]);const text=String(value);return text.length>70?`${text.slice(0,70)}…`:text}
 function renderFormField([key,label,type,opts,required]){
   const title=t(fieldTextKeys[label]||label);
   const isRequired=!!required&&!editingId;
   const value=form[key]||'';
   const onChange=e=>setForm({...form,[key]:e.target.value});
   if(type==='password')return <PasswordField key={key} label={title} helpText={t('passwordRequirementsHint')} minLength="7" maxLength="72" required={isRequired} value={value} onChange={onChange}/>;
   if(type==='college')return <label key={key}>{title}<select required={!!required||Boolean(page==='Profile requests'&&form.college_id)} value={form.college_id||''} onChange={e=>setForm({...form,college_id:e.target.value,department_id:''})}><option value="">{t('selectCollege')}</option>{collegeOptions.map((college,index)=><option key={`${college.college_id}-${index}`} value={college.college_id}>{college.name}</option>)}</select></label>;
   if(type==='department')return <label key={key}>{title}<select required={!!required||Boolean(page==='Profile requests'&&form.college_id)} disabled={!form.college_id} value={form.department_id||''} onChange={e=>setForm({...form,department_id:e.target.value})}><option value="">{t('selectDepartment')}</option>{departmentOptions.map((department,index)=><option key={`${department.department_id}-${index}`} value={department.department_id}>{department.name}</option>)}</select></label>;
  if(type==='employee'||(['Payroll','Evaluations'].includes(page)&&key==='emp_id'))return <label key={key}>{title}<select required={isRequired} value={form.emp_id||''} onChange={e=>setForm({...form,emp_id:e.target.value})}><option value="">{t(type==='employee'?'selectEmployeeOptional':'select')}</option>{staffOptions.map((employee,index)=><option key={`${employee.emp_id}-${index}`} value={employee.emp_id}>{employee.staff_no||`Employee #${employee.emp_id}`} — {employee.full_name}</option>)}</select></label>;
   if(type==='textarea')return <label key={key}>{title}<textarea required={isRequired} value={value} onChange={onChange}/></label>;
   if(type==='select')return <label key={key}>{title}<select required={isRequired} value={value} onChange={onChange}><option value="">{t('select')}</option>{opts.map((option,index)=><option key={`${option}-${index}`} value={option}>{t(optionTextKeys[option]||option)}</option>)}</select></label>;
   return <label key={key}>{title}<input type={type} required={isRequired} min={type==='number'?0:undefined} minLength={key==='username'||key==='account_username'?4:undefined} maxLength={key==='username'||key==='account_username'?80:undefined} onInvalid={e=>{if((key==='username'||key==='account_username')&&e.currentTarget.value.length>0&&e.currentTarget.value.length<4)e.currentTarget.setCustomValidity(t('usernameTooShort'))}} onInput={e=>e.currentTarget.setCustomValidity('')} value={value} onChange={onChange}/></label>;
 }
 return <div className="panel"><div className="panel-heading"><div><h3>{t(textKeys[page])||page}</h3><p>{page==='Profile requests'&&user.role==='Employee'?t('profileRequestDescription'):t('recordsByRole')}</p></div><div className="table-actions">{page==='Employees'&&<><input className="search" placeholder={t('searchEmployees')} value={search} onChange={e=>setSearch(e.target.value)} onKeyDown={e=>e.key==='Enter'&&refresh()}/ ><select aria-label={t('filterStaffNumber')} value={staffFilter} onChange={e=>setStaffFilter(e.target.value)}><option value="">{t('allStaffNumbers')}</option>{rows.map((employee,index)=><option key={`${employee.emp_id}-${index}`} value={employee.emp_id}>{employee.staff_no||`Employee #${employee.emp_id}`}</option>)}</select></>}{page==='Employees'&&user.role==='Administrator'&&<button className="secondary" onClick={()=>{setShowLinkForm(!showLinkForm);setShowForm(false);setEditingId(null)}}>{showLinkForm?t('cancel'):t('createLoginAccount')}</button>}{canCreate&&<button className="primary" onClick={()=>{if(showForm){setShowForm(false);setEditingId(null);setForm({})}else{setShowForm(true);setEditingId(null);setForm({})}setShowLinkForm(false)}}>{showForm?t('cancel'):editingId?t('edit'):createLabel}</button>}</div></div>
 {success&&<div className="alert alert-success" role="status">{success}</div>}
 {page==='Profile requests'&&user.role==='Employee'&&!user.emp_id&&<div className="alert" role="status">{t('employeeNotLinked')}</div>}
 {showLinkForm&&page==='Employees'&&user.role==='Administrator'&&<form className="record-form" onSubmit={submitLinkedAccount}><label>{t('staffNumber')}<select value={linkForm.emp_id} onChange={e=>setLinkForm({...linkForm,emp_id:e.target.value})} required><option value="">{t('selectStaffMember')}</option>{rows.map((employee,index)=><option key={`${employee.emp_id}-${index}`} value={employee.emp_id}>{employee.staff_no||`Employee #${employee.emp_id}`} — {employee.full_name}</option>)}</select></label><label>{t('loginUsername')}<input value={linkForm.username} onChange={e=>setLinkForm({...linkForm,username:e.target.value})} minLength="4" maxLength="80" onInvalid={e=>{if(e.currentTarget.value.length>0&&e.currentTarget.value.length<4)e.currentTarget.setCustomValidity(t('usernameTooShort'))}} onInput={e=>e.currentTarget.setCustomValidity('')} required/></label><PasswordField label={t('initialPassword')} helpText={t('passwordRequirementsHint')} minLength="7" maxLength="72" value={linkForm.password} onChange={e=>setLinkForm({...linkForm,password:e.target.value})} required/><label>{t('accountRole')}<select value={linkForm.role} onChange={e=>setLinkForm({...linkForm,role:e.target.value})} required>{['Administrator','HR Officer','Department Head','Employee','Management'].map((role,index)=><option key={`${role}-${index}`} value={role}>{t(optionTextKeys[role])}</option>)}</select></label><div className="form-footer"><button className="primary" disabled={linkBusy||!rows.length}>{linkBusy?t('creatingAccount'):t('createLoginAccount')}</button></div></form>}
 {showForm&&page==='Notifications'&&canManageNotifications&&<form className="record-form" onSubmit={submitNotification}><label>Recipient<select value={form.user_id||''} onChange={e=>setForm({...form,user_id:e.target.value})} required><option value="">Select recipient</option>{notificationUsers.map((account,index)=><option key={`${account.user_id}-${index}`} value={account.user_id}>{account.username}{account.full_name?` - ${account.full_name}`:''}</option>)}</select></label><label>Title<input value={form.title||''} onChange={e=>setForm({...form,title:e.target.value})} maxLength="160"/></label><label>Type<select value={form.type||'system'} onChange={e=>setForm({...form,type:e.target.value})}><option value="system">System</option><option value="announcement">Announcement</option><option value="warning">Warning</option><option value="reminder">Reminder</option></select></label><label>Message<textarea value={form.message||''} onChange={e=>setForm({...form,message:e.target.value})} rows="4" required /></label><label>Details JSON<textarea value={form.details||''} onChange={e=>setForm({...form,details:e.target.value})} rows="4" placeholder='{"status":"Updated"}' /></label><div className="form-footer"><button className="primary" type="submit">{editingId?'Save':'Create notification'}</button><button className="secondary" type="button" onClick={()=>{setShowForm(false);setEditingId(null);setForm({})}}>Cancel</button></div></form>}
 {showForm&&<form className="record-form" onSubmit={submit}>
   {(definitions[page]||[]).filter(([key])=>key.startsWith('account_')?user.role==='Administrator'&&!editingId:true).map(renderFormField)}
   {page==='Employees'&&user.role==='Administrator'&&!editingId&&<p className="small muted">{t('optionalLoginNotice')}</p>}
   {page==='Profile requests'&&<p className="small muted">{t('profileRequestDescription')}</p>}
   <div className="form-footer"><button className="primary" disabled={busy}>{busy?t('saving'):editingId?t('save'):page==='Profile requests'?t('sendRequest'):t('saveRecord')}</button><button className="secondary" type="button" onClick={()=>{setShowForm(false);setEditingId(null);setForm({})}}>{t('cancel')}</button></div>
 </form>}
 {loading?<div className="empty"><RefreshCw className="spin"/> {t('loadingRecords')}</div>:page==='Reports'?<ReportView data={rows[0]}/>:page==='Notifications'?<div className="notification-list">{rows.map((r,index)=><div className="notification" key={`${r.not_id??'notification'}-${index}`}><Bell size={18}/><div className="notification-body"><div className="notification-meta"><strong>{r.title||'MAU HRMS notification'}</strong>{canManageNotifications&&<div className="mini-actions"><button type="button" className="mini" onClick={()=>startEdit(r)}>Edit</button><button type="button" className="mini danger" onClick={()=>removeRecord(r)}>Delete</button></div>}</div><p>{r.message}</p><small>{r.created_at}</small>{r.details&&Object.keys(r.details).length>0&&<pre className="notification-details">{JSON.stringify(r.details,null,2)}</pre>}</div></div>)}{!rows.length&&<Empty/>}</div>:!visibleRows.length?<Empty/>:<div className="table-wrap"><table><thead><tr>{columns.map(c=><th key={c}>{columnTitle(c)}</th>)}{(actionable||roleRequests||profileActionable||canManage)&&<th>{t('action')}</th>}{page==='Vacancies'&&['Administrator','HR Officer'].includes(user.role)&&<th>{t('action')}</th>}</tr></thead><tbody>{visibleRows.map((r,i)=><tr key={`${r.user_id??r.college_id??r.emp_id??r.leave_id??r.vacancy_id??r.att_id??r.pay_id??r.eval_id??r.dept_id??r.app_id??r.request_id??r.role_request_id??'row'}-${i}`}>{columns.map(c=><td key={c}>{c==='status'?<span className={`status ${String(r[c]).toLowerCase().replaceAll(' ','-')}`}>{displayValue(c,r[c])}</span>:displayValue(c,r[c])}</td>)}{actionable&&<td>{((user.role==='Administrator'&&['Pending Head','Pending HR'].includes(r.status))||(r.status==='Pending Head'&&user.role==='Department Head')||(r.status==='Pending HR'&&user.role==='HR Officer'))?['approve','reject'].map(decisionChoice=><button key={decisionChoice} className={`mini ${decisionChoice==='approve'?'success':'danger'}`} onClick={()=>decision(r.leave_id,decisionChoice)}>{t(decisionChoice)}</button>):<span className="muted">—</span>}</td>}{roleRequests&&<td>{r.status==='Pending'?<><button className="mini success" onClick={()=>reviewRoleRequest(r.request_id,'approve')}>{t('approve')}</button> <button className="mini danger" onClick={()=>reviewRoleRequest(r.request_id,'reject')}>{t('reject')}</button></>:<span className="muted">{t('reviewed')}</span>}</td>}{profileActionable&&<td>{r.status==='Pending'?<><button className="mini success" onClick={()=>reviewProfileRequest(r.request_id,'approve')}>{t('approve')}</button> <button className="mini danger" onClick={()=>reviewProfileRequest(r.request_id,'reject')}>{t('reject')}</button></>:<span className="muted">{t('reviewed')}</span>}</td>}{canManage&&<td><button className="mini" onClick={()=>startEdit(r)}>{t('edit')}</button> <button className="mini danger" onClick={()=>removeRecord(r)}>{t('delete')}</button></td>}{page==='Vacancies'&&['Administrator','HR Officer'].includes(user.role)&&<td><select className="mini-select" value={r.status} onChange={e=>updateStatus(r.vacancy_id,e.target.value)}><option value="Draft">{t('draft')}</option><option value="Published">{t('published')}</option><option value="Closed">{t('closed')}</option></select></td>}</tr>)}</tbody></table></div>}</div>
}
function ReportView({data}){
 const {t}=useTranslation();
 if(!data||typeof data!=='object'||Array.isArray(data))return <Empty/>;
 const groups=Object.entries(data);
 if(!groups.length)return <Empty/>;
 return <div className="reports-grid">{groups.map(([key,items])=><div className="report-box" key={key}>
   <h4>{t(fieldTextKeys[key.replaceAll('_',' ')]||textKeys[key]||'reportData')}</h4>
   {Array.isArray(items)?items.map((item,index)=>{
     const fields=item&&typeof item==='object'&&!Array.isArray(item)?Object.entries(item):[];
     return <div className="report-line" key={`${item?.id??item?.emp_id??item?.department??item?.pay_month??index}-${index}`}>
       {fields.length?fields.map(([field,value])=><span key={field}><b>{columnTextKeys[field]?t(columnTextKeys[field]):field.replaceAll('_',' ')}:</b> {value}</span>):<span>{item===null||item===undefined?'':String(item)}</span>}
     </div>;
   }):<small>{t('noData')}</small>}
   {Array.isArray(items)&&items.length===0&&<small>{t('noData')}</small>}
 </div>)}</div>;
}
function Empty(){const {t}=useTranslation();return <div className="empty"><div className="empty-icon"><Users size={23}/></div><strong>{t('noRecords')}</strong><p>{t('recordsAvailable')}</p></div>}

createRoot(document.getElementById('root')).render(<App/>);
